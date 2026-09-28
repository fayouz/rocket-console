<?php

namespace App\Controller;

use App\Billing\CatalogueProvider;
use App\Billing\InvalidSubscription;
use App\Billing\QuoteCalculator;
use App\Billing\SubscriptionDraft;
use App\Entity\Account;
use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Operator figures (MRR estimated from the quotes, accounts by status, trials ending) and the audit log. */
#[IsGranted('ROLE_ADMIN')]
final class OverviewController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly CatalogueProvider $catalogue, private readonly QuoteCalculator $quotes)
    {
    }

    #[Route('/api/overview', name: 'api_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        return $this->json($this->figures());
    }

    /** @return array<string, mixed> */
    public function figures(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $catalogue = $this->catalogue->catalogue();
        $byStatus = array_fill_keys(Account::STATUSES, 0);
        $mrr = $trialPipeline = 0;
        $trials = $bricks = [];
        foreach ($this->em->getRepository(Account::class)->findAll() as $account) {
            /** @var Account $account */
            ++$byStatus[$account->getStatus()];
            $sub = $account->currentSubscription($now);
            $monthly = 0;
            if (null !== $sub) {
                try {
                    $quote = $this->quotes->quote($catalogue, SubscriptionDraft::of($sub));
                    $monthly = $quote['monthlyEquivalentCents'];
                    if (\in_array($account->getStatus(), ['trial', 'active'], true)) {
                        foreach ($quote['enabledBricks'] as $b) {
                            $bricks[$b] = ($bricks[$b] ?? 0) + 1;
                        }
                    }
                } catch (InvalidSubscription) {
                }
            }
            if ('active' === $account->getStatus()) {
                $mrr += $monthly;
            }
            if ('trial' === $account->getStatus()) {
                $trialPipeline += $monthly;
                $trials[] = ['slug' => $account->getSlug(), 'name' => $account->getName(), 'trialEndsAt' => $account->getTrialEndsAt()?->format(\DATE_ATOM), 'monthlyCents' => $monthly];
            }
        }
        usort($trials, static fn ($a, $b) => ($a['trialEndsAt'] ?? '9') <=> ($b['trialEndsAt'] ?? '9'));
        arsort($bricks);

        return ['mrrCents' => $mrr, 'arrCents' => $mrr * 12, 'trialPipelineCents' => $trialPipeline, 'accountsByStatus' => $byStatus, 'trialsEnding' => \array_slice($trials, 0, 10), 'bricksInUse' => $bricks, 'currency' => $catalogue->rules->getCurrency()];
    }

    /** Query: account (slug), limit (≤ 500, default 100). Newest first. */
    #[Route('/api/audit-logs', name: 'api_audit_logs', methods: ['GET'])]
    public function audit(Request $request): JsonResponse
    {
        $criteria = [];
        if ('' !== $account = (string) $request->query->get('account')) {
            $criteria['accountSlug'] = $account;
        }

        return $this->json(array_map(static fn (AuditLog $l) => $l->toArray(), $this->em->getRepository(AuditLog::class)->findBy($criteria, ['occurredAt' => 'DESC'], min(500, max(1, $request->query->getInt('limit', 100))))));
    }
}
