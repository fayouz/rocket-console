<?php

namespace App\Command;

use App\Billing\CatalogueSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('console:catalogue:seed', 'Creates the missing bricks, options and plans of the default catalogue (never overwrites).')]
final class CatalogueSeedCommand
{
    public function __construct(private readonly CatalogueSeeder $seeder)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d élément(s) créé(s).', $this->seeder->seed()));

        return Command::SUCCESS;
    }
}
