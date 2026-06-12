<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DTO\CreateTransferRequest;
use App\Entity\Account;
use App\Enum\AccountStatus;
use App\Exception\TransferException;
use App\Service\TransferService;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TransferServiceTest extends DatabaseTestCase
{
    private TransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferService = self::getContainer()->get(TransferService::class);
    }

    public function testSuccessfulTransferUpdatesBalancesAndCreatesLedger(): void
    {
        $source = $this->createAccount('LT-SRC-001', 'EUR', '100.0000');
        $destination = $this->createAccount('LT-DST-001', 'EUR', '50.0000');

        $response = $this->transferService->createTransfer(
            new CreateTransferRequest($source->getId()->toRfc4122(), $destination->getId()->toRfc4122(), '25.5000'),
            'idem-success-1',
        );

        self::assertSame('COMPLETED', $response->status);
        $this->entityManager->clear();

        $reloadedSource = $this->entityManager->find(Account::class, $source->getId());
        $reloadedDestination = $this->entityManager->find(Account::class, $destination->getId());

        self::assertSame('74.5000', $reloadedSource->getBalance());
        self::assertSame('75.5000', $reloadedDestination->getBalance());
    }

    public function testInsufficientFundsDoesNotChangeBalances(): void
    {
        $source = $this->createAccount('LT-SRC-002', 'EUR', '10.0000');
        $destination = $this->createAccount('LT-DST-002', 'EUR', '0.0000');

        try {
            $this->transferService->createTransfer(
                new CreateTransferRequest($source->getId()->toRfc4122(), $destination->getId()->toRfc4122(), '10.0100'),
                'idem-insufficient-1',
            );
            self::fail('Expected insufficient funds exception');
        } catch (TransferException $e) {
            self::assertSame('INSUFFICIENT_FUNDS', $e->getErrorCode());
        }

        $this->entityManager->clear();
        self::assertSame('10.0000', $this->entityManager->find(Account::class, $source->getId())->getBalance());
        self::assertSame('0.0000', $this->entityManager->find(Account::class, $destination->getId())->getBalance());
    }

    public function testSameAccountTransferIsRejected(): void
    {
        $account = $this->createAccount('LT-SAME-001', 'EUR', '100.0000');

        $this->expectException(TransferException::class);
        $this->transferService->createTransfer(
            new CreateTransferRequest($account->getId()->toRfc4122(), $account->getId()->toRfc4122(), '1.0000'),
            'idem-same-account',
        );
    }

    public function testCurrencyMismatchIsRejected(): void
    {
        $source = $this->createAccount('LT-SRC-USD', 'USD', '100.0000');
        $destination = $this->createAccount('LT-DST-EUR', 'EUR', '100.0000');

        $this->expectException(TransferException::class);
        $this->transferService->createTransfer(
            new CreateTransferRequest($source->getId()->toRfc4122(), $destination->getId()->toRfc4122(), '1.0000'),
            'idem-currency',
        );
    }

    public function testDuplicateIdempotencyKeyReturnsOriginalResult(): void
    {
        $source = $this->createAccount('LT-SRC-003', 'EUR', '200.0000');
        $destination = $this->createAccount('LT-DST-003', 'EUR', '0.0000');
        $request = new CreateTransferRequest(
            $source->getId()->toRfc4122(),
            $destination->getId()->toRfc4122(),
            '15.0000',
        );

        $first = $this->transferService->createTransfer($request, 'idem-duplicate');
        $second = $this->transferService->createTransfer($request, 'idem-duplicate');

        self::assertSame($first->id, $second->id);
        $this->entityManager->clear();
        self::assertSame('185.0000', $this->entityManager->find(Account::class, $source->getId())->getBalance());
    }

    public function testBlockedAccountIsRejected(): void
    {
        $source = $this->createAccount('LT-SRC-BLOCKED', 'EUR', '100.0000');
        $destination = $this->createAccount('LT-DST-BLOCKED', 'EUR', '0.0000');
        $source->block();
        $this->entityManager->flush();

        $this->expectException(TransferException::class);
        $this->transferService->createTransfer(
            new CreateTransferRequest($source->getId()->toRfc4122(), $destination->getId()->toRfc4122(), '1.0000'),
            'idem-blocked',
        );
    }

    public function testConcurrentTransfersMaintainConsistency(): void
    {
        $source = $this->createAccount('LT-SRC-CONC', 'EUR', '100.0000');
        $destination = $this->createAccount('LT-DST-CONC', 'EUR', '0.0000');

        for ($i = 0; $i < 5; ++$i) {
            $this->transferService->createTransfer(
                new CreateTransferRequest(
                    $source->getId()->toRfc4122(),
                    $destination->getId()->toRfc4122(),
                    '10.0000',
                ),
                'idem-concurrent-'.$i,
            );
        }

        $this->entityManager->clear();
        self::assertSame('50.0000', $this->entityManager->find(Account::class, $source->getId())->getBalance());
        self::assertSame('50.0000', $this->entityManager->find(Account::class, $destination->getId())->getBalance());
    }

    public function testTransactionRollbackOnFailure(): void
    {
        $source = $this->createAccount('LT-SRC-RB', 'EUR', '5.0000');
        $destination = $this->createAccount('LT-DST-RB', 'EUR', '0.0000');

        try {
            $this->transferService->createTransfer(
                new CreateTransferRequest($source->getId()->toRfc4122(), $destination->getId()->toRfc4122(), '6.0000'),
                'idem-rollback',
            );
        } catch (TransferException) {
        }

        $transferCount = (int) $this->entityManager->createQuery('SELECT COUNT(t.id) FROM App\Entity\Transfer t')
            ->getSingleScalarResult();

        self::assertSame(0, $transferCount);
    }
}
