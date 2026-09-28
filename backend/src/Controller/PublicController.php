<?php

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Billing\CatalogueProvider;
use App\Billing\InvalidSubscription;
use App\Billing\QuoteCalculator;
use App\Billing\SubscriptionDraft;
use App\Billing\SubscriptionPayload;
use App\Entity\Account;
use App\Entity\Brick;
use App\Entity\Member;
use App\Entity\Option;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Signup\RateLimiter;
use App\Signup\VerificationMailer;
use App\Support\Payload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public, unauthenticated endpoints of the self-service sign-up (page /inscription): the catalogue without internal
 * fields, a live quote, the sign-up itself and the e-mail verification. All rate-limited per client IP.
 */
final class PublicController extends AbstractController
{
    /** Minimum time between the form display and its submission (seconds): faster is a bot. */
    public const MIN_FILL_SECONDS = 3;
    public const VERIFICATION_DAYS = 7;
    public const NEXT_STEPS = [
        ['key' => 'verify-email', 'label' => 'Confirmer votre adresse e-mail (lien reçu par e-mail)'],
        ['key' => 'channel-manager', 'label' => 'Connecter votre channel manager'],
        ['key' => 'properties', 'label' => 'Ajouter vos logements'],
        ['key' => 'team', 'label' => 'Inviter votre équipe'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CatalogueProvider $catalogue,
        private readonly QuoteCalculator $quotes,
        private readonly RateLimiter $limiter,
        private readonly VerificationMailer $mailer,
        private readonly AuditLogger $audit,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
    }

    /** Active plans, bricks and options with their prices, and the pricing rules. No ids nor internal fields. */
    #[Route('/api/public/catalogue', name: 'api_public_catalogue', methods: ['GET'])]
    public function catalogue(Request $request): JsonResponse
    {
        $this->limiter->hit('catalogue', $request);
        $c = $this->catalogue->catalogue();
        $rules = $c->rules->toArray();

        return $this->json([
            'plans' => array_values(array_map(static fn (Plan $p) => array_intersect_key($p->toArray(), array_flip(['code', 'name', 'bricks', 'unit', 'unitPriceCents', 'description'])), array_filter($c->plans, static fn (Plan $p) => $p->toArray()['active']))),
            'bricks' => array_values(array_map(static fn (Brick $b) => array_intersect_key($b->toArray(), array_flip(['code', 'name', 'family', 'depends', 'unit', 'monthlyPriceCents', 'description'])), array_filter($c->bricks, static fn (Brick $b) => $b->toArray()['active']))),
            'options' => array_values(array_map(static fn (Option $o) => array_intersect_key($o->toArray(), array_flip(['code', 'name', 'brick', 'grants', 'unit', 'monthlyPriceCents'])), array_filter($c->options, static fn (Option $o) => $o->toArray()['active']))),
            'pricing' => array_intersect_key($rules, array_flip(['minimumMonthlyCents', 'volumeTiers', 'yearlyFreeMonths', 'trialDays', 'currency'])),
        ]);
    }

    /** Quote of an offer (same body as a subscription). 422 with "errors" when it breaks the catalogue rules. */
    #[Route('/api/public/quote', name: 'api_public_quote', methods: ['POST'])]
    public function quote(Request $request): JsonResponse
    {
        $this->limiter->hit('quote', $request);
        $sub = new Subscription(new Account('draft', 'draft'));
        SubscriptionPayload::apply($sub, new Payload($this->body($request)));
        try {
            return $this->json($this->quotes->quote($this->catalogue->catalogue(), SubscriptionDraft::of($sub)));
        } catch (InvalidSubscription $e) {
            return $this->json(['detail' => $e->getMessage(), 'errors' => $e->errors], 422);
        }
    }

    /**
     * {"offer": {plan?, bricks?, options?, quantities, period}, "account": {name, slug?, country?, vatNumber?},
     *  "owner": {name, email, password? | authProvider: "rocket-auth"}, "acceptTerms": true, "formStartedAt": unix,
     *  "website": "" (honeypot)}. Creates the account in trial, its subscription and the owner (pending
     * verification). Idempotent by owner e-mail while unverified: a repeat renews the link (200, created false).
     */
    #[Route('/api/public/signup', name: 'api_public_signup', methods: ['POST'])]
    public function signup(Request $request): JsonResponse
    {
        $this->limiter->hit('signup', $request);
        $body = $this->body($request);
        $p = new Payload($body);

        // anti-bot: a filled honeypot or a form sent too fast gets a neutral answer and creates nothing
        $startedAt = $p->int('formStartedAt');
        if (null !== $p->string('website', 255) || null === $startedAt || time() - $startedAt < self::MIN_FILL_SECONDS) {
            return $this->json(['created' => false, 'status' => 'pending'], 202);
        }
        if (true !== ($body['acceptTerms'] ?? null)) {
            throw new HttpException(422, 'Veuillez accepter les conditions d’utilisation.');
        }

        $accountData = new Payload($p->array('account'));
        $ownerData = new Payload($p->array('owner'));
        $email = mb_strtolower((string) $ownerData->string('email', 180, true));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'Adresse e-mail invalide.');
        }

        $now = new \DateTimeImmutable();
        $existing = $this->em->getRepository(Member::class)->findBy(['email' => $email]);
        foreach ($existing as $member) {
            if ('signup' === $member->getAccount()->getSource() && 'owner' === $member->getRole() && !$member->isVerified()) {
                $token = $member->requestVerification($now->modify(\sprintf('+%d days', self::VERIFICATION_DAYS)));
                $this->em->flush();

                return $this->json($this->result($member, $token, false), 200);
            }
        }
        if ([] !== $existing) {
            throw new HttpException(409, 'Un compte existe déjà pour cette adresse : connectez-vous ou contactez-nous.');
        }

        $rocketAuth = 'rocket-auth' === $ownerData->string('authProvider', 20);
        $password = $rocketAuth ? null : (string) $ownerData->string('password', 4096, true);
        if (null !== $password && mb_strlen($password) < 10) {
            throw new HttpException(422, 'Le mot de passe doit contenir au moins 10 caractères.');
        }

        $name = (string) $accountData->string('name', 120, true);
        $account = new Account($this->slug($accountData->string('slug', 64), $name), $name);
        $account->setSource('signup')->setStatus('trial')
            ->setTrialEndsAt(new \DateTimeImmutable(\sprintf('today +%d days', $this->catalogue->rules()->getTrialDays())))
            ->setBillingName($name)->setBillingEmail($email)
            ->setVatNumber($accountData->string('vatNumber', 32));
        if (null !== $country = $accountData->string('country', 2)) {
            if (!preg_match('/^[A-Za-z]{2}$/', $country)) {
                throw new HttpException(422, 'Champ « country » : code pays ISO à 2 lettres.');
            }
            $account->setCountry($country);
        }

        $offer = $p->array('offer');
        if (null === ($offer['plan'] ?? null) && [] === ($offer['bricks'] ?? [])) {
            throw new HttpException(422, 'Choisissez une offre ou au moins une brique.');
        }
        $sub = new Subscription($account, new \DateTimeImmutable('today'));
        SubscriptionPayload::apply($sub, new Payload(array_intersect_key($offer, array_flip(['plan', 'bricks', 'options', 'quantities', 'period']))));
        try {
            $quote = $this->quotes->quote($this->catalogue->catalogue(), SubscriptionDraft::of($sub));
        } catch (InvalidSubscription $e) {
            throw new HttpException(422, $e->getMessage());
        }
        $account->addSubscription($sub);

        $owner = (new Member($account, $email, 'owner'))->setName($ownerData->string('name', 120, true));
        if (null !== $password) {
            $owner->setPasswordHash(password_hash($password, \PASSWORD_DEFAULT));
        }
        $token = $owner->requestVerification($now->modify(\sprintf('+%d days', self::VERIFICATION_DAYS)));
        $account->addMember($owner);

        $this->em->persist($account);
        $this->audit->log('account.signup', 'account', $account->getSlug(), $account->getSlug(), [
            'email' => $email, 'offer' => $sub->toArray(), 'monthlyEquivalentCents' => $quote['monthlyEquivalentCents'], 'authProvider' => $rocketAuth ? 'rocket-auth' : 'password',
        ]);
        $this->em->flush();

        return $this->json($this->result($owner, $token, true) + ['quote' => $quote], 201);
    }

    /** {"token"}: confirms the owner's e-mail. 422 when the link is unknown or expired. */
    #[Route('/api/public/verify-email', name: 'api_public_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $this->limiter->hit('verify', $request);
        $token = (string) (new Payload($this->body($request)))->string('token', 128, true);
        $member = $this->em->getRepository(Member::class)->findOneBy(['verificationTokenHash' => hash('sha256', $token)]);
        if (null === $member || !$member->verify($token, new \DateTimeImmutable())) {
            throw new HttpException(422, 'Lien de vérification invalide ou expiré.');
        }
        $this->audit->log('member.verify_email', 'member', $member->getId()->toRfc4122(), $member->getAccount()->getSlug());
        $this->em->flush();

        return $this->json(['verified' => true, 'account' => ['slug' => $member->getAccount()->getSlug(), 'name' => $member->getAccount()->getName()], 'nextSteps' => \array_slice(self::NEXT_STEPS, 1)]);
    }

    /** @return array<string, mixed> */
    private function result(Member $owner, string $token, bool $created): array
    {
        $account = $owner->getAccount();
        $sent = $this->mailer->send($owner, $token);
        $verification = ['sent' => $sent, 'email' => $owner->getEmail()];
        if (!$sent && !$this->mailer->isConfigured() && 'prod' !== $this->environment) {
            $verification['devUrl'] = $this->mailer->verificationUrl($token);
        }

        return [
            'created' => $created,
            'account' => array_intersect_key($account->toArray(), array_flip(['slug', 'name', 'status', 'trialEndsAt', 'country'])),
            'subscription' => $account->currentSubscription(new \DateTimeImmutable())?->toArray(),
            'owner' => ['email' => $owner->getEmail(), 'name' => $owner->getName(), 'verified' => $owner->isVerified()],
            'verification' => $verification,
            'nextSteps' => self::NEXT_STEPS,
        ];
    }

    /** A free slug: the requested one (409 if taken) or one derived from the name, suffixed -2, -3… if needed. */
    private function slug(?string $requested, string $name): string
    {
        $repo = $this->em->getRepository(Account::class);
        if (null !== $requested) {
            if (!preg_match('/^'.AccountController::SLUG.'$/', $requested)) {
                throw new HttpException(422, 'Champ « slug » : minuscules, chiffres et tirets.');
            }
            if (null !== $repo->findOneBy(['slug' => $requested])) {
                throw new HttpException(409, \sprintf('L’identifiant « %s » est déjà pris.', $requested));
            }

            return $requested;
        }
        $base = substr(AccountController::slugify($name), 0, 58);
        $slug = $base;
        for ($i = 2; null !== $repo->findOneBy(['slug' => $slug]); ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            throw new HttpException(400, 'Corps JSON invalide.');
        }
    }
}
