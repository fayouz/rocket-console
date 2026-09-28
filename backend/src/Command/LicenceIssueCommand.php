<?php

namespace App\Command;

use App\Entity\Account;
use App\Licence\Entitlements;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('console:licence:issue', 'Prints the signed licence key (entitlements JWS) of an account, for a self-hosted instance.')]
final class LicenceIssueCommand
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Entitlements $entitlements)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Account slug')] string $account, #[Option('Print the claims too')] bool $claims = false): int
    {
        $a = $this->em->getRepository(Account::class)->findOneBy(['slug' => $account]);
        if (null === $a) {
            $io->error(\sprintf('Compte inconnu : %s', $account));

            return Command::FAILURE;
        }
        $licence = $this->entitlements->licence($a);
        if ($claims) {
            $io->writeln((string) json_encode($licence['claims'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
        }
        $io->writeln($licence['token']);

        return Command::SUCCESS;
    }
}
