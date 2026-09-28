<?php

namespace App\Billing;

use App\Entity\Brick;
use App\Entity\Option;
use App\Entity\Plan;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Default catalogue of the suite (prices of the plan, cents excl. VAT per month): created when missing, never
 * overwritten (edit it in the console). Idempotent.
 */
final class CatalogueSeeder
{
    /** code => [name, family, unit, price, depends, description] */
    public const BRICKS = [
        'auth' => ['Rocket Auth', 'middleware', 'flat', 500, [], 'Connexion unique, comptes et organisations.'],
        'cloud' => ['Rocket Cloud', 'middleware', 'per_place', 500, [], 'Fichiers et documents partagés.'],
        'mailer' => ['Rocket Mailer', 'middleware', 'per_place', 500, [], 'E-mails, modèles et boîtes partagées.'],
        'print' => ['Rocket Print', 'middleware', 'per_place', 500, [], 'Impression et étiquettes.'],
        'docfusion' => ['Rocket Doc Fusion', 'middleware', 'per_place', 500, [], 'Fusion de documents (contrats, factures).'],
        'dispatch' => ['Rocket Dispatch', 'middleware', 'per_place', 500, ['mailer'], 'Envois et notifications multicanal.'],
        'cast' => ['Rocket Cast', 'middleware', 'per_screen', 500, [], 'Affichage sur écrans (TV d’accueil).'],
        'host' => ['Rocket Host', 'business', 'per_property', 1000, [], 'Tableau de bord des logements (ex-LoussaHousing).'],
        'pms' => ['Rocket PMS', 'business', 'per_place', 500, ['place'], 'Réservations, canaux et calendrier.'],
        'place' => ['Rocket Place', 'business', 'per_place', 500, [], 'Lieux, accès et équipements.'],
        'clean' => ['Rocket Clean', 'business', 'per_place', 500, ['place'], 'Ménages, check-lists et photos.'],
        'stock' => ['Rocket Stock', 'business', 'per_place', 500, ['place'], 'Stock, courses et consommations.'],
    ];

    /** code => [name, brick, grants, unit, price] */
    public const OPTIONS = [
        'host-clean' => ['Ménage', 'host', 'clean', 'per_property', 500],
        'host-places' => ['Lieux', 'host', 'place', 'per_property', 400],
        'host-stock' => ['Stock', 'host', 'stock', 'per_property', 200],
        'shared-inbox' => ['Boîte partagée', 'mailer', null, 'per_mailbox', 900],
    ];

    /** code => [name, bricks, unit, price, description] */
    public const PLANS = [
        'rocket-host' => ['Rocket Host', ['host'], 'per_property', 1000, 'Le tableau de bord des logements.'],
        'rocket-location' => ['Rocket Location', ['host', 'clean', 'place', 'stock', 'mailer'], 'per_property', 2200, 'Host + Clean + Place + Stock + Mailer.'],
    ];

    public function __construct(private readonly EntityManagerInterface $em, private readonly CatalogueProvider $provider)
    {
    }

    public function seed(): int
    {
        $created = 0;
        $position = 0;
        foreach (self::BRICKS as $code => [$name, $family, $unit, $price, $depends, $description]) {
            ++$position;
            if (null === $this->em->getRepository(Brick::class)->findOneBy(['code' => $code])) {
                $this->em->persist((new Brick($code, $name))->setFamily($family)->setUnit($unit)->setMonthlyPriceCents($price)->setDepends($depends)->setDescription($description)->setPosition($position));
                ++$created;
            }
        }
        foreach (self::OPTIONS as $code => [$name, $brick, $grants, $unit, $price]) {
            if (null === $this->em->getRepository(Option::class)->findOneBy(['code' => $code])) {
                $this->em->persist((new Option($code, $name, $brick))->setGrants($grants)->setUnit($unit)->setMonthlyPriceCents($price));
                ++$created;
            }
        }
        foreach (self::PLANS as $code => [$name, $bricks, $unit, $price, $description]) {
            if (null === $this->em->getRepository(Plan::class)->findOneBy(['code' => $code])) {
                $this->em->persist((new Plan($code, $name))->setBricks($bricks)->setUnit($unit)->setUnitPriceCents($price)->setDescription($description));
                ++$created;
            }
        }
        $this->em->flush();
        $this->provider->rules();

        return $created;
    }
}
