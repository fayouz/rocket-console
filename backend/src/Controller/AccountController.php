<?php

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Billing\CatalogueProvider;
use App\Billing\InvalidSubscription;
use App\Billing\QuoteCalculator;
use App\Billing\SubscriptionDraft;
use App\Billing\SubscriptionPayload;
use App\Entity\Account;
use App\Entity\Member;
use App\Entity\Subscription;
use App\Licence\Entitlements;
use App\Support\Payload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Customer accounts, their members and subscriptions (operator only: ROLE_ADMIN). */
#[IsGranted('ROLE_ADMIN')]
final class AccountController extends AbstractController
{
    public const SLUG = '[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CatalogueProvider $catalogue,
        private readonly QuoteCalculator $quotes,
        private readonly Entitlements $entitlements,
        private readonly AuditLogger $audit,
    ) {
    }

    /** Query: status, q (name or slug). Each account comes with its current subscription and monthly quote. */
    #[Route('/api/accounts', name: 'api_accounts', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Account::class)->createQueryBuilder('a')->orderBy('a.name', 'ASC');
        if (\in_array($status = $request->query->get('status'), Account::STATUSES, true)) {
            $qb->andWhere('a.status = :s')->setParameter('s', $status);
        }
        if ($request->query->getBoolean('new')) {
            $qb->andWhere("a.source = 'signup' AND a.signupReviewedAt IS NULL");
        }
        if ('' !== $q = trim((string) $request->query->get('q'))) {
            $qb->andWhere('LOWER(a.name) LIKE :q OR a.slug LIKE :q')->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        $now = new \DateTimeImmutable();

        return $this->json(array_map(function (Account $a) use ($now) {
            $sub = $a->currentSubscription($now);

            return $a->toArray() + ['subscription' => $sub?->toArray(), 'monthlyCents' => $this->monthly($sub), 'members' => $a->getMembers()->count()];
        }, $qb->getQuery()->getResult()));
    }

    #[Route('/api/accounts', name: 'api_account_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $name = (string) $p->string('name', 120, true);
        $slug = $p->string('slug', 64) ?? self::slugify($name);
        if (!preg_match('/^'.self::SLUG.'$/', $slug)) {
            throw new HttpException(422, 'Champ « slug » : minuscules, chiffres et tirets.');
        }
        if (null !== $this->em->getRepository(Account::class)->findOneBy(['slug' => $slug])) {
            throw new HttpException(409, \sprintf('Le compte « %s » existe déjà.', $slug));
        }
        $account = new Account($slug, $name);
        $account->setStatus($p->choice('status', Account::STATUSES, 'trial'));
        if ('trial' === $account->getStatus()) {
            $account->setTrialEndsAt(new \DateTimeImmutable(\sprintf('today +%d days', $this->catalogue->rules()->getTrialDays())));
        }
        $this->apply($account, $p);
        if (null !== $owner = $p->string('ownerEmail', 180)) {
            $account->addMember(new Member($account, $this->email($owner), 'owner'));
        }
        $this->em->persist($account);
        $this->audit->log('account.create', 'account', $slug, $slug, $request->toArray());
        $this->em->flush();

        return $this->json($this->detail($account), 201);
    }

    #[Route('/api/accounts/{slug}', name: 'api_account', methods: ['GET'], requirements: ['slug' => self::SLUG])]
    public function show(string $slug): JsonResponse
    {
        return $this->json($this->detail($this->account($slug)));
    }

    #[Route('/api/accounts/{slug}', name: 'api_account_update', methods: ['PATCH', 'PUT'], requirements: ['slug' => self::SLUG])]
    public function update(string $slug, Request $request): JsonResponse
    {
        $account = $this->account($slug);
        $p = new Payload($request->toArray());
        if ($p->has('name')) {
            $account->setName((string) $p->string('name', 120, true));
        }
        if ($p->has('status')) {
            $account->setStatus($p->choice('status', Account::STATUSES));
        }
        $this->apply($account, $p);
        $this->audit->log('account.update', 'account', $slug, $slug, $request->toArray());
        $this->em->flush();

        return $this->json($this->detail($account));
    }

    /** The operator acknowledges a self-service sign-up: the « Nouveau » badge goes away. */
    #[Route('/api/accounts/{slug}/signup-reviewed', name: 'api_account_signup_reviewed', methods: ['POST'], requirements: ['slug' => self::SLUG])]
    public function signupReviewed(string $slug): JsonResponse
    {
        $account = $this->account($slug);
        $account->markSignupReviewed(new \DateTimeImmutable());
        $this->audit->log('account.signup_reviewed', 'account', $slug, $slug);
        $this->em->flush();

        return $this->json($this->detail($account));
    }

    /** Only a closed account can be deleted (with its members and subscriptions; the audit log stays). */
    #[Route('/api/accounts/{slug}', name: 'api_account_delete', methods: ['DELETE'], requirements: ['slug' => self::SLUG])]
    public function delete(string $slug): Response
    {
        $account = $this->account($slug);
        if ('closed' !== $account->getStatus()) {
            throw new HttpException(409, 'Clôture le compte avant de le supprimer.');
        }
        $this->em->remove($account);
        $this->audit->log('account.delete', 'account', $slug, $slug);
        $this->em->flush();

        return new Response(null, 204);
    }

    #[Route('/api/accounts/{slug}/members', name: 'api_member_create', methods: ['POST'], requirements: ['slug' => self::SLUG])]
    public function addMember(string $slug, Request $request): JsonResponse
    {
        $account = $this->account($slug);
        $p = new Payload($request->toArray());
        $email = $this->email((string) $p->string('email', 180, true));
        foreach ($account->getMembers() as $m) {
            if ($m->getEmail() === $email) {
                throw new HttpException(409, 'Ce membre existe déjà.');
            }
        }
        $member = (new Member($account, $email, $p->choice('role', Member::ROLES, 'member')))->setName($p->string('name', 120));
        $account->addMember($member);
        $this->audit->log('member.create', 'member', $member->getId()->toRfc4122(), $slug, $request->toArray());
        $this->em->flush();

        return $this->json($member->toArray(), 201);
    }

    #[Route('/api/accounts/{slug}/members/{id}', name: 'api_member_update', methods: ['PATCH'], requirements: ['slug' => self::SLUG, 'id' => Requirement::UUID])]
    public function updateMember(string $slug, string $id, Request $request): JsonResponse
    {
        $member = $this->member($slug, $id);
        $p = new Payload($request->toArray());
        if ($p->has('role')) {
            $member->setRole($p->choice('role', Member::ROLES));
        }
        if ($p->has('name')) {
            $member->setName($p->string('name', 120));
        }
        $this->audit->log('member.update', 'member', $id, $slug, $request->toArray());
        $this->em->flush();

        return $this->json($member->toArray());
    }

    #[Route('/api/accounts/{slug}/members/{id}', name: 'api_member_delete', methods: ['DELETE'], requirements: ['slug' => self::SLUG, 'id' => Requirement::UUID])]
    public function removeMember(string $slug, string $id): Response
    {
        $member = $this->member($slug, $id);
        $member->getAccount()->getMembers()->removeElement($member);
        $this->audit->log('member.delete', 'member', $id, $slug, ['email' => $member->getEmail()]);
        $this->em->flush();

        return new Response(null, 204);
    }

    /**
     * {"plan"?, "bricks"?: [], "options"?: [], "quantities"?: {properties, places, screens, mailboxes}, "period"?,
     * "startsAt"?: Y-m-d, "endsAt"?: Y-m-d}. Validated against the catalogue (dependencies). A new subscription ends
     * the one in force the day it starts.
     */
    #[Route('/api/accounts/{slug}/subscriptions', name: 'api_subscription_create', methods: ['POST'], requirements: ['slug' => self::SLUG])]
    public function subscribe(string $slug, Request $request): JsonResponse
    {
        $account = $this->account($slug);
        $p = new Payload($request->toArray());
        $sub = new Subscription($account, $p->date('startsAt') ?? new \DateTimeImmutable('today'));
        SubscriptionPayload::apply($sub, $p);
        $quote = $this->validated($sub);
        $previous = $account->currentSubscription($sub->getStartsAt());
        if (null !== $previous && $previous->getStartsAt() < $sub->getStartsAt()) {
            $previous->setEndsAt($sub->getStartsAt());
        } elseif (null !== $previous) {
            $previous->setEndsAt($previous->getStartsAt());
        }
        $account->addSubscription($sub);
        $this->audit->log('subscription.create', 'subscription', $sub->getId()->toRfc4122(), $slug, $request->toArray());
        $this->em->flush();

        return $this->json($sub->toArray() + ['quote' => $quote], 201);
    }

    #[Route('/api/subscriptions/{id}', name: 'api_subscription_update', methods: ['PATCH', 'PUT'], requirements: ['id' => Requirement::UUID])]
    public function updateSubscription(string $id, Request $request): JsonResponse
    {
        $sub = $this->em->find(Subscription::class, $id) ?? throw new NotFoundHttpException('Abonnement inconnu.');
        $p = new Payload($request->toArray());
        if ($p->has('startsAt')) {
            $sub->setStartsAt($p->date('startsAt') ?? throw new HttpException(422, 'Champ « startsAt » requis.'));
        }
        SubscriptionPayload::apply($sub, $p);
        $quote = $this->validated($sub);
        $this->audit->log('subscription.update', 'subscription', $id, $sub->getAccount()->getSlug(), $request->toArray());
        $this->em->flush();

        return $this->json($sub->toArray() + ['quote' => $quote]);
    }

    #[Route('/api/subscriptions/{id}', name: 'api_subscription_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    public function deleteSubscription(string $id): Response
    {
        $sub = $this->em->find(Subscription::class, $id) ?? throw new NotFoundHttpException('Abonnement inconnu.');
        $sub->getAccount()->getSubscriptions()->removeElement($sub);
        $this->audit->log('subscription.delete', 'subscription', $id, $sub->getAccount()->getSlug(), $sub->toArray());
        $this->em->flush();

        return new Response(null, 204);
    }

    /** Quote of a draft, nothing saved: same body as a subscription. 422 with "errors" when it breaks the catalogue rules. */
    #[Route('/api/quote', name: 'api_quote', methods: ['POST'])]
    public function quote(Request $request): JsonResponse
    {
        $sub = new Subscription(new Account('draft', 'draft'));
        SubscriptionPayload::apply($sub, new Payload($request->toArray()));

        return $this->json($this->validated($sub));
    }

    /** Signed licence key of the account (also: `bin/console console:licence:issue <account>`). */
    #[Route('/api/accounts/{slug}/licence', name: 'api_account_licence', methods: ['GET'], requirements: ['slug' => self::SLUG])]
    public function licence(string $slug): JsonResponse
    {
        $account = $this->account($slug);
        try {
            $licence = $this->entitlements->licence($account);
        } catch (\LogicException $e) {
            throw new HttpException(503, $e->getMessage());
        }
        $this->audit->log('licence.issue', 'account', $slug, $slug, ['expiresAt' => $licence['claims']['expiresAt'], 'bricks' => $licence['claims']['bricks']]);
        $this->em->flush();

        return $this->json($licence);
    }

    /** @return array<string, mixed> */
    private function detail(Account $account): array
    {
        $now = new \DateTimeImmutable();
        $sub = $account->currentSubscription($now);
        $quote = null;
        if (null !== $sub) {
            try {
                $quote = $this->quotes->quote($this->catalogue->catalogue(), SubscriptionDraft::of($sub));
            } catch (InvalidSubscription $e) {
                $quote = ['errors' => $e->errors];
            }
        }

        return $account->toArray() + [
            'members' => array_map(static fn (Member $m) => $m->toArray(), $account->getMembers()->toArray()),
            'subscriptions' => array_map(static fn (Subscription $s) => $s->toArray() + ['current' => $s === $sub], $account->getSubscriptions()->toArray()),
            'subscription' => $sub?->toArray(),
            'quote' => $quote,
            'entitlements' => $this->entitlements->compute($account, $now),
        ];
    }

    private function monthly(?Subscription $sub): ?int
    {
        if (null === $sub) {
            return null;
        }
        try {
            return $this->quotes->quote($this->catalogue->catalogue(), SubscriptionDraft::of($sub))['monthlyEquivalentCents'];
        } catch (InvalidSubscription) {
            return null;
        }
    }

    /** @return array<string, mixed> the quote */
    private function validated(Subscription $sub): array
    {
        try {
            return $this->quotes->quote($this->catalogue->catalogue(), SubscriptionDraft::of($sub));
        } catch (InvalidSubscription $e) {
            throw new HttpException(422, $e->getMessage());
        }
    }

    private function apply(Account $a, Payload $p): void
    {
        if ($p->has('trialEndsAt')) {
            $a->setTrialEndsAt($p->date('trialEndsAt'));
        }
        foreach (['billingName' => 120, 'billingAddress' => 255, 'vatNumber' => 32, 'notes' => 5000] as $key => $max) {
            if ($p->has($key)) {
                $a->{'set'.ucfirst($key)}($p->string($key, $max));
            }
        }
        if ($p->has('billingEmail')) {
            $email = $p->string('billingEmail', 180);
            $a->setBillingEmail(null === $email ? null : $this->email($email));
        }
        if ($p->has('country')) {
            $country = $p->string('country', 2);
            if (null !== $country && !preg_match('/^[A-Za-z]{2}$/', $country)) {
                throw new HttpException(422, 'Champ « country » : code pays ISO à 2 lettres.');
            }
            $a->setCountry($country);
        }
    }

    private function email(string $email): string
    {
        return false !== filter_var($email, \FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : throw new HttpException(422, 'Adresse e-mail invalide.');
    }

    private function account(string $slug): Account
    {
        return $this->em->getRepository(Account::class)->findOneBy(['slug' => $slug]) ?? throw new NotFoundHttpException('Compte inconnu.');
    }

    private function member(string $slug, string $id): Member
    {
        $member = $this->em->find(Member::class, $id);

        return null !== $member && $member->getAccount()->getSlug() === $slug ? $member : throw new NotFoundHttpException('Membre inconnu.');
    }

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $name)), '-'));

        return '' === $slug ? 'compte' : substr($slug, 0, 64);
    }
}
