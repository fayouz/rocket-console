<?php

namespace App\Command;

use App\Billing\CatalogueSeeder;
use App\Entity\Account;
use App\Entity\Member;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Command\DemoSeederInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demo data: the default catalogue, the first customer "loussahousing" (Faez, Rocket Location for 2 properties,
 * active) and a trial account (Rocket Host, 1 property, trial ending in 12 days). Idempotent (by slug).
 */
final class ConsoleDemoSeeder implements DemoSeederInterface
{
    public function __construct(private readonly CatalogueSeeder $catalogue, private readonly EntityManagerInterface $em)
    {
    }

    public function seed(array $users, SymfonyStyle $io): void
    {
        $this->catalogue->seed();
        $repo = $this->em->getRepository(Account::class);
        if (null === $repo->findOneBy(['slug' => 'loussahousing'])) {
            $a = (new Account('loussahousing', 'LoussaHousing'))->setStatus('active')->setBillingName('Faez Bouloussa')->setBillingEmail('faez@loussahousing.example')->setCountry('FR');
            $a->addMember((new Member($a, 'faez@loussahousing.example', 'owner'))->setName('Faez'));
            $a->addSubscription((new Subscription($a, new \DateTimeImmutable('first day of this month')))->setPlan('rocket-location')->setQuantities(['properties' => 2, 'places' => 2, 'mailboxes' => 1])->setPeriod('monthly'));
            $this->em->persist($a);
        }
        if (null === $repo->findOneBy(['slug' => 'gite-des-oliviers'])) {
            $t = (new Account('gite-des-oliviers', 'Gîte des Oliviers'))->setStatus('trial')->setTrialEndsAt(new \DateTimeImmutable('today +12 days'))->setBillingEmail('contact@gite-des-oliviers.example')->setCountry('FR');
            $t->addMember(new Member($t, 'contact@gite-des-oliviers.example', 'owner'));
            $t->addSubscription((new Subscription($t, new \DateTimeImmutable('today -18 days')))->setPlan('rocket-host')->setQuantities(['properties' => 1]));
            $this->em->persist($t);
        }
        $this->em->flush();
        $io->writeln('Rocket Console : catalogue, comptes « loussahousing » (actif) et « gite-des-oliviers » (essai).');
    }
}
