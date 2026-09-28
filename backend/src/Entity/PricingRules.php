<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;

/**
 * The global pricing rules (a single row, id 1): monthly minimum, volume discount tiers (on the number of
 * properties or places, whichever is higher), months offered on a yearly period, trial length.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalogue_pricing')]
class PricingRules
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column]
    private int $minimumMonthlyCents = 2900;

    /** @var list<array{min: int, percent: int}> */
    #[ORM\Column(type: 'json')]
    private array $volumeTiers = [['min' => 10, 'percent' => 15], ['min' => 30, 'percent' => 25]];

    #[ORM\Column]
    private int $yearlyFreeMonths = 2;

    #[ORM\Column]
    private int $trialDays = 30;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    use TrackedTrait;

    public function getMinimumMonthlyCents(): int { return $this->minimumMonthlyCents; }
    public function setMinimumMonthlyCents(int $v): static { $this->minimumMonthlyCents = $v; return $this; }
    /** @return list<array{min: int, percent: int}> */
    public function getVolumeTiers(): array { return $this->volumeTiers; }
    /** @param list<array{min: int, percent: int}> $v */
    public function setVolumeTiers(array $v): static { usort($v, static fn ($a, $b) => $a['min'] <=> $b['min']); $this->volumeTiers = array_values($v); return $this; }
    public function getYearlyFreeMonths(): int { return $this->yearlyFreeMonths; }
    public function setYearlyFreeMonths(int $v): static { $this->yearlyFreeMonths = $v; return $this; }
    public function getTrialDays(): int { return $this->trialDays; }
    public function setTrialDays(int $v): static { $this->trialDays = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['minimumMonthlyCents' => $this->minimumMonthlyCents, 'volumeTiers' => $this->volumeTiers, 'yearlyFreeMonths' => $this->yearlyFreeMonths, 'trialDays' => $this->trialDays, 'currency' => $this->currency];
    }
}
