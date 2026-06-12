<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateTransferRequest;
use App\DTO\TransferResponse;
use App\Entity\LedgerEntry;
use App\Entity\Transfer;
use App\Enum\LedgerEntryType;
use App\Exception\TransferException;
use App\Repository\AccountRepository;
use App\Repository\TransferRepository;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class TransferService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accountRepository,
        private readonly TransferRepository $transferRepository,
        private readonly IdempotencyService $idempotencyService,
        private readonly LoggerInterface $transferLogger,
        private readonly LoggerInterface $auditLogger,
        private readonly float $maxTransferAmount,
        private readonly int $maxDeadlockRetries,
    ) {
    }

    public function createTransfer(CreateTransferRequest $request, string $idempotencyKey): TransferResponse
    {
        $requestHash = $request->requestHash();
        $cached = $this->idempotencyService->resolve($idempotencyKey, $requestHash);
        if ($cached !== null) {
            $this->transferLogger->info('Duplicate idempotent transfer request', [
                'idempotency_key' => $idempotencyKey,
                'transfer_id' => $cached->id,
            ]);

            return $cached;
        }

        $attempt = 0;
        while (true) {
            try {
                return $this->executeTransfer($request, $idempotencyKey, $requestHash);
            } catch (DeadlockException $e) {
                ++$attempt;
                if ($attempt > $this->maxDeadlockRetries) {
                    $this->transferLogger->error('Transfer failed after deadlock retries', [
                        'idempotency_key' => $idempotencyKey,
                        'attempts' => $attempt,
                    ]);
                    throw $e;
                }

                $this->transferLogger->warning('Deadlock detected, retrying transfer', [
                    'idempotency_key' => $idempotencyKey,
                    'attempt' => $attempt,
                ]);

                usleep(50_000 * $attempt);
            }
        }
    }

    public function getTransfer(Uuid $id): TransferResponse
    {
        $transfer = $this->transferRepository->findByUuid($id);
        if ($transfer === null) {
            throw TransferException::transferNotFound($id->toRfc4122());
        }

        return TransferResponse::fromEntity($transfer);
    }

    private function executeTransfer(
        CreateTransferRequest $request,
        string $idempotencyKey,
        string $requestHash,
    ): TransferResponse {
        $sourceId = Uuid::fromString((string) $request->sourceAccountId);
        $destinationId = Uuid::fromString((string) $request->destinationAccountId);
        $amount = (string) $request->amount;

        if ($sourceId->equals($destinationId)) {
            throw TransferException::sameAccount();
        }

        if (bccomp($amount, (string) $this->maxTransferAmount, 4) > 0) {
            throw TransferException::amountExceedsLimit(number_format($this->maxTransferAmount, 4, '.', ''));
        }

        $this->entityManager->beginTransaction();

        try {
            [$sourceAccount, $destinationAccount] = $this->accountRepository->findPairForUpdate(
                $sourceId,
                $destinationId,
            );

            if ($sourceAccount === null) {
                throw TransferException::accountNotFound($sourceId->toRfc4122());
            }

            if ($destinationAccount === null) {
                throw TransferException::accountNotFound($destinationId->toRfc4122());
            }

            $this->assertAccountActive($sourceAccount->getStatus()->value, 'source');
            $this->assertAccountActive($destinationAccount->getStatus()->value, 'destination');

            if ($sourceAccount->getCurrency() !== $destinationAccount->getCurrency()) {
                throw TransferException::currencyMismatch();
            }

            if (!$sourceAccount->hasSufficientFunds($amount)) {
                throw TransferException::insufficientFunds();
            }

            $transfer = new Transfer(
                $sourceAccount,
                $destinationAccount,
                $amount,
                $idempotencyKey,
                $requestHash,
            );

            $sourceAccount->debit($amount);
            $destinationAccount->credit($amount);

            $this->entityManager->persist($transfer);
            $this->entityManager->persist(new LedgerEntry(
                $transfer,
                $sourceAccount,
                LedgerEntryType::DEBIT,
                $amount,
                $sourceAccount->getBalance(),
            ));
            $this->entityManager->persist(new LedgerEntry(
                $transfer,
                $destinationAccount,
                LedgerEntryType::CREDIT,
                $amount,
                $destinationAccount->getBalance(),
            ));

            $transfer->markCompleted();
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->idempotencyService->cache($idempotencyKey, $transfer, $requestHash);

            $response = TransferResponse::fromEntity($transfer);

            $this->transferLogger->info('Transfer completed', [
                'transfer_id' => $response->id,
                'amount' => $response->amount,
                'currency' => $response->currency,
            ]);

            $this->auditLogger->info('Transfer audit record', $response->toArray());

            return $response;
        } catch (TransferException $e) {
            $this->entityManager->rollback();
            $this->transferLogger->warning('Transfer validation failed', [
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function assertAccountActive(string $status, string $role): void
    {
        if ($status !== 'ACTIVE') {
            throw TransferException::accountNotActive($status);
        }
    }
}
