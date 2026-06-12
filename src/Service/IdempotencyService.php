<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\TransferResponse;
use App\Entity\Transfer;
use App\Exception\TransferException;
use App\Infrastructure\Redis\RedisClientInterface;
use App\Repository\TransferRepository;

final class IdempotencyService
{
    private const string KEY_PREFIX = 'idempotency:';

    public function __construct(
        private readonly TransferRepository $transferRepository,
        private readonly RedisClientInterface $redis,
        private readonly int $ttlSeconds,
    ) {
    }

    public function resolve(string $idempotencyKey, string $requestHash): ?TransferResponse
    {
        $cached = $this->redis->get(self::KEY_PREFIX.$idempotencyKey);
        if ($cached !== null) {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($cached, true, flags: JSON_THROW_ON_ERROR);

            if (($payload['request_hash'] ?? '') !== $requestHash) {
                throw TransferException::idempotencyConflict();
            }

            return $this->hydrateResponse($payload);
        }

        $existing = $this->transferRepository->findByIdempotencyKey($idempotencyKey);
        if ($existing === null) {
            return null;
        }

        if ($existing->getRequestHash() !== $requestHash) {
            throw TransferException::idempotencyConflict();
        }

        $response = TransferResponse::fromEntity($existing);
        $this->cache($idempotencyKey, $existing, $requestHash);

        return $response;
    }

    public function cache(string $idempotencyKey, Transfer $transfer, string $requestHash): void
    {
        $response = TransferResponse::fromEntity($transfer);
        $payload = json_encode([
            ...$response->toArray(),
            'request_hash' => $requestHash,
        ], JSON_THROW_ON_ERROR);

        $this->redis->setex(self::KEY_PREFIX.$idempotencyKey, $this->ttlSeconds, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateResponse(array $payload): TransferResponse
    {
        return new TransferResponse(
            id: (string) $payload['id'],
            sourceAccountId: (string) $payload['source_account_id'],
            destinationAccountId: (string) $payload['destination_account_id'],
            amount: (string) $payload['amount'],
            currency: (string) $payload['currency'],
            status: (string) $payload['status'],
            failureReason: $payload['failure_reason'] ?? null,
            createdAt: (string) $payload['created_at'],
            completedAt: $payload['completed_at'] ?? null,
        );
    }
}
