<?php

namespace App\Billing;

use App\Entity\Subscription;

/** What is priced and validated: a subscription, saved or not. */
final class SubscriptionDraft
{
    /**
     * @param list<string>       $bricks     bricks à la carte (besides the plan)
     * @param list<string>       $options
     * @param array<string, int> $quantities properties, places, screens, mailboxes
     */
    public function __construct(
        public readonly ?string $plan = null,
        public readonly array $bricks = [],
        public readonly array $options = [],
        public readonly array $quantities = [],
        public readonly string $period = 'monthly',
    ) {
    }

    public static function of(Subscription $s): self
    {
        return new self($s->getPlan(), $s->getBricks(), $s->getOptions(), $s->getQuantities(), $s->getPeriod());
    }

    /** Quantity counted by a pricing unit (flat: 1). */
    public function quantityFor(string $unit): int
    {
        return match ($unit) {
            'per_property' => max(0, (int) ($this->quantities['properties'] ?? 0)),
            'per_place' => max(0, (int) ($this->quantities['places'] ?? 0)),
            'per_screen' => max(0, (int) ($this->quantities['screens'] ?? 0)),
            'per_mailbox' => max(0, (int) ($this->quantities['mailboxes'] ?? 0)),
            default => 1,
        };
    }
}
