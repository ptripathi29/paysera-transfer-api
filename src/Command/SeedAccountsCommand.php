<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-accounts', description: 'Seed demo accounts for local testing')]
final class SeedAccountsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $accounts = [
            new Account('LT0010000000000001', 'EUR', '1000.0000'),
            new Account('LT0010000000000002', 'EUR', '500.0000'),
            new Account('LT0010000000000003', 'EUR', '0.0000'),
        ];

        foreach ($accounts as $account) {
            $this->entityManager->persist($account);
            $io->writeln(sprintf('Created account %s (%s)', $account->getAccountNumber(), $account->getId()));
        }

        $this->entityManager->flush();
        $io->success('Demo accounts seeded.');

        return Command::SUCCESS;
    }
}
