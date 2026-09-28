<?php

namespace App\Billing;

use App\Entity\Brick;
use App\Entity\Option;
use App\Entity\Plan;
use App\Entity\PricingRules;

/** Snapshot of the catalogue used to validate and price a subscription (no database access: unit-testable). */
final class Catalogue
{
    /** @var array<string, Brick> */
    public readonly array $bricks;
    /** @var array<string, Option> */
    public readonly array $options;
    /** @var array<string, Plan> */
    public readonly array $plans;

    /**
     * @param iterable<Brick>  $bricks
     * @param iterable<Option> $options
     * @param iterable<Plan>   $plans
     */
    public function __construct(iterable $bricks, iterable $options, iterable $plans, public readonly PricingRules $rules)
    {
        $b = $o = $p = [];
        foreach ($bricks as $x) {
            $b[$x->getCode()] = $x;
        }
        foreach ($options as $x) {
            $o[$x->getCode()] = $x;
        }
        foreach ($plans as $x) {
            $p[$x->getCode()] = $x;
        }
        $this->bricks = $b;
        $this->options = $o;
        $this->plans = $p;
    }
}
