<?php

namespace App\Tests\Unit;

use App\Billing\Catalogue;
use App\Billing\CatalogueSeeder;
use App\Billing\InvalidSubscription;
use App\Billing\QuoteCalculator;
use App\Billing\SubscriptionDraft;
use App\Entity\Brick;
use App\Entity\Option;
use App\Entity\Plan;
use App\Entity\PricingRules;
use PHPUnit\Framework\TestCase;

/** Prices of the plan: Host 10 €, Location 22 €, bricks 5 €, options, minimum 29 €, −15 % ≥ 10, −25 % ≥ 30, 2 months free. */
final class QuoteCalculatorTest extends TestCase
{
    private Catalogue $catalogue;
    private QuoteCalculator $calculator;

    protected function setUp(): void
    {
        $bricks = $options = $plans = [];
        foreach (CatalogueSeeder::BRICKS as $code => [$name, $family, $unit, $price, $depends]) {
            $bricks[] = (new Brick($code, $name))->setFamily($family)->setUnit($unit)->setMonthlyPriceCents($price)->setDepends($depends);
        }
        foreach (CatalogueSeeder::OPTIONS as $code => [$name, $brick, $grants, $unit, $price]) {
            $options[] = (new Option($code, $name, $brick))->setGrants($grants)->setUnit($unit)->setMonthlyPriceCents($price);
        }
        foreach (CatalogueSeeder::PLANS as $code => [$name, $b, $unit, $price]) {
            $plans[] = (new Plan($code, $name))->setBricks($b)->setUnit($unit)->setUnitPriceCents($price);
        }
        $this->catalogue = new Catalogue($bricks, $options, $plans, new PricingRules());
        $this->calculator = new QuoteCalculator();
    }

    public function testMinimumAppliesToSmallSubscriptions(): void
    {
        $q = $this->calculator->quote($this->catalogue, new SubscriptionDraft('rocket-host', quantities: ['properties' => 1]));
        self::assertSame(1000, $q['subtotalCents']);
        self::assertSame(1900, $q['minimumTopUpCents']);
        self::assertSame(2900, $q['monthlyCents']);
        self::assertSame(['host'], $q['enabledBricks']);
    }

    public function testRocketLocationForTwoProperties(): void
    {
        $q = $this->calculator->quote($this->catalogue, new SubscriptionDraft('rocket-location', quantities: ['properties' => 2]));
        self::assertSame(4400, $q['monthlyCents']);
        self::assertSame(0, $q['minimumTopUpCents']);
        self::assertSame(['clean', 'host', 'mailer', 'place', 'stock'], $q['enabledBricks']);
    }

    public function testOptionsAndBricksAlaCarte(): void
    {
        // Host 3 properties + Ménage (grants clean) + Lieux (grants place) + Cast 2 screens
        $q = $this->calculator->quote($this->catalogue, new SubscriptionDraft('rocket-host', ['cast', 'host'], ['host-clean', 'host-places'], ['properties' => 3, 'screens' => 2]));
        self::assertSame(3 * 1000 + 2 * 500 + 3 * 500 + 3 * 400, $q['subtotalCents']);
        self::assertCount(4, $q['lines'], 'host is included in the plan, not billed twice');
        self::assertSame(['cast', 'clean', 'host', 'place'], $q['enabledBricks']);
    }

    public function testVolumeTiers(): void
    {
        $ten = $this->calculator->quote($this->catalogue, new SubscriptionDraft('rocket-host', quantities: ['properties' => 10]));
        self::assertSame(15, $ten['discountPercent']);
        self::assertSame(8500, $ten['monthlyCents']);
        $thirty = $this->calculator->quote($this->catalogue, new SubscriptionDraft('rocket-location', quantities: ['properties' => 30]));
        self::assertSame(25, $thirty['discountPercent']);
        self::assertSame(49500, $thirty['monthlyCents']);
        // the tier counts places too (the highest of properties and places)
        $places = $this->calculator->quote($this->catalogue, new SubscriptionDraft(null, ['place', 'pms'], quantities: ['places' => 12]));
        self::assertSame(15, $places['discountPercent']);
        self::assertSame(10200, $places['monthlyCents']);
    }

    public function testYearlyGivesTwoMonths(): void
    {
        $q = $this->calculator->quote($this->catalogue, new SubscriptionDraft('rocket-location', quantities: ['properties' => 5], period: 'yearly'));
        self::assertSame(11000, $q['monthlyCents']);
        self::assertSame(110000, $q['periodTotalCents']);
        self::assertSame(9167, $q['monthlyEquivalentCents']);
    }

    public function testNothingSubscribedCostsNothing(): void
    {
        self::assertSame(0, $this->calculator->quote($this->catalogue, new SubscriptionDraft())['monthlyCents']);
    }

    public function testDependenciesComeFromTheCatalogue(): void
    {
        try {
            $this->calculator->quote($this->catalogue, new SubscriptionDraft(null, ['pms'], quantities: ['places' => 1]));
            self::fail('PMS without Place');
        } catch (InvalidSubscription $e) {
            self::assertSame(['La brique « pms » demande la brique « place ».'], $e->errors);
        }
    }

    public function testInvalidDrafts(): void
    {
        $cases = [
            [new SubscriptionDraft('nope', quantities: ['properties' => 1]), 'Offre inconnue'],
            [new SubscriptionDraft('rocket-host'), 'demande une quantité'],
            [new SubscriptionDraft(null, ['cloud'], ['host-clean'], ['places' => 1]), 'demande la brique « host »'],
            [new SubscriptionDraft(null, ['ghost']), 'Brique inconnue'],
        ];
        foreach ($cases as [$draft, $message]) {
            try {
                $this->calculator->quote($this->catalogue, $draft);
                self::fail($message);
            } catch (InvalidSubscription $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }
}
