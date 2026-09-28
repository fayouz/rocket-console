<?php

namespace App\Billing;

/**
 * Price of a subscription, in cents (excl. VAT):
 * 1. lines: the plan (unit price × its unit quantity), the bricks à la carte not included in the plan, the options;
 * 2. volume discount: the highest tier whose "min" ≤ max(properties, places), on the subtotal;
 * 3. monthly minimum (only when something is subscribed);
 * 4. yearly period: 12 − yearlyFreeMonths months billed.
 */
final class QuoteCalculator
{
    public function __construct(private readonly SubscriptionRules $rules = new SubscriptionRules())
    {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidSubscription
     */
    public function quote(Catalogue $catalogue, SubscriptionDraft $draft): array
    {
        $enabled = $this->rules->enabledBricks($catalogue, $draft);
        $lines = [];
        $included = [];
        if (null !== $draft->plan) {
            $plan = $catalogue->plans[$draft->plan];
            $included = $plan->getBricks();
            $lines[] = $this->line('plan', $plan->getCode(), $plan->getName(), $plan->getUnit(), $draft->quantityFor($plan->getUnit()), $plan->getUnitPriceCents());
        }
        foreach ($draft->bricks as $code) {
            if (\in_array($code, $included, true)) {
                continue;
            }
            $brick = $catalogue->bricks[$code];
            $lines[] = $this->line('brick', $code, $brick->getName(), $brick->getUnit(), $draft->quantityFor($brick->getUnit()), $brick->getMonthlyPriceCents());
        }
        foreach ($draft->options as $code) {
            $option = $catalogue->options[$code];
            $lines[] = $this->line('option', $code, $option->getName(), $option->getUnit(), $draft->quantityFor($option->getUnit()), $option->getMonthlyPriceCents());
        }

        $subtotal = array_sum(array_column($lines, 'totalCents'));
        $volume = max($draft->quantityFor('per_property'), $draft->quantityFor('per_place'));
        $percent = 0;
        foreach ($catalogue->rules->getVolumeTiers() as $tier) {
            if ($volume >= $tier['min']) {
                $percent = max($percent, (int) $tier['percent']);
            }
        }
        $discount = (int) round($subtotal * $percent / 100);
        $afterDiscount = $subtotal - $discount;
        $minimum = $catalogue->rules->getMinimumMonthlyCents();
        $topUp = $afterDiscount > 0 && $afterDiscount < $minimum ? $minimum - $afterDiscount : 0;
        $monthly = $afterDiscount + $topUp;
        $freeMonths = 'yearly' === $draft->period ? max(0, min(11, $catalogue->rules->getYearlyFreeMonths())) : 0;
        $periodTotal = 'yearly' === $draft->period ? $monthly * (12 - $freeMonths) : $monthly;

        return [
            'currency' => $catalogue->rules->getCurrency(),
            'period' => $draft->period,
            'enabledBricks' => $enabled,
            'lines' => $lines,
            'subtotalCents' => $subtotal,
            'volume' => $volume,
            'discountPercent' => $percent,
            'discountCents' => $discount,
            'minimumCents' => $minimum,
            'minimumTopUpCents' => $topUp,
            'monthlyCents' => $monthly,
            'yearlyFreeMonths' => $freeMonths,
            'periodTotalCents' => $periodTotal,
            // What the subscription is worth per month (MRR), a yearly period included.
            'monthlyEquivalentCents' => 'yearly' === $draft->period ? (int) round($periodTotal / 12) : $monthly,
        ];
    }

    /** @return array<string, mixed> */
    private function line(string $kind, string $code, string $name, string $unit, int $quantity, int $unitPrice): array
    {
        return ['kind' => $kind, 'code' => $code, 'name' => $name, 'unit' => $unit, 'unitLabel' => Units::label($unit), 'quantity' => $quantity, 'unitPriceCents' => $unitPrice, 'totalCents' => $quantity * $unitPrice];
    }
}
