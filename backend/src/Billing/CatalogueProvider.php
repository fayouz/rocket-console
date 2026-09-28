<?php

namespace App\Billing;

use App\Entity\Brick;
use App\Entity\Option;
use App\Entity\Plan;
use App\Entity\PricingRules;
use Doctrine\ORM\EntityManagerInterface;

/** Loads the catalogue snapshot from the database (the pricing rules row is created on first use). */
final class CatalogueProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function rules(): PricingRules
    {
        $rules = $this->em->find(PricingRules::class, 1);
        if (null === $rules) {
            $rules = new PricingRules();
            $this->em->persist($rules);
            $this->em->flush();
        }

        return $rules;
    }

    public function catalogue(): Catalogue
    {
        return new Catalogue(
            $this->em->getRepository(Brick::class)->findBy([], ['position' => 'ASC', 'code' => 'ASC']),
            $this->em->getRepository(Option::class)->findBy([], ['code' => 'ASC']),
            $this->em->getRepository(Plan::class)->findBy([], ['code' => 'ASC']),
            $this->rules(),
        );
    }
}
