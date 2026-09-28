<?php

namespace App\Licence;

use App\Billing\CatalogueProvider;
use App\Billing\InvalidSubscription;
use App\Billing\SubscriptionDraft;
use App\Billing\SubscriptionRules;
use App\Entity\Account;

/**
 * What an account may use, derived from its subscription in force: enabled bricks, options, quotas and until when.
 * A suspended or closed account, an expired trial or no subscription: no brick. The same document, signed, is the
 * licence key of a self-hosted instance.
 */
final class Entitlements
{
    public function __construct(
        private readonly CatalogueProvider $catalogue,
        private readonly SubscriptionRules $rules,
        private readonly LicenceSigner $signer,
    ) {
    }

    /** @return array<string, mixed> */
    public function compute(Account $account, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $sub = $account->currentSubscription($now);
        $trialOver = 'trial' === $account->getStatus() && null !== $account->getTrialEndsAt() && $account->getTrialEndsAt() <= $now;
        $usable = null !== $sub && \in_array($account->getStatus(), ['trial', 'active'], true) && !$trialOver;
        $bricks = [];
        if ($usable) {
            try {
                $bricks = $this->rules->enabledBricks($this->catalogue->catalogue(), SubscriptionDraft::of($sub));
            } catch (InvalidSubscription) {
                $bricks = [];
            }
        }
        if ('trial' === $account->getStatus() && null !== $account->getTrialEndsAt()) {
            $expires = $account->getTrialEndsAt();
        } else {
            // Renewed while the subscription runs: a short validity, refreshed by the bricks (or a new licence).
            $expires = $now->modify('yearly' === $sub?->getPeriod() ? '+380 days' : '+35 days');
            if (null !== $sub?->getEndsAt() && $sub->getEndsAt() < $expires) {
                $expires = $sub->getEndsAt();
            }
        }

        return [
            'account' => ['slug' => $account->getSlug(), 'name' => $account->getName(), 'status' => $account->getStatus()],
            'active' => $usable && [] !== $bricks,
            'plan' => $usable ? $sub->getPlan() : null,
            'bricks' => $bricks,
            'options' => $usable ? $sub->getOptions() : [],
            'quotas' => $usable ? $sub->getQuantities() : array_fill_keys(['properties', 'places', 'screens', 'mailboxes'], 0),
            'period' => $sub?->getPeriod(),
            'issuedAt' => $now->format(\DATE_ATOM),
            'expiresAt' => $expires->format(\DATE_ATOM),
        ];
    }

    /** @return array{token: string, algorithm: string, claims: array<string, mixed>} */
    public function licence(Account $account, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $doc = $this->compute($account, $now);
        $claims = ['iss' => LicenceSigner::ISSUER, 'sub' => $account->getSlug(), 'iat' => $now->getTimestamp(), 'exp' => (new \DateTimeImmutable($doc['expiresAt']))->getTimestamp()] + $doc;

        return ['token' => $this->signer->sign($claims), 'algorithm' => $this->signer->algorithm(), 'claims' => $claims];
    }
}
