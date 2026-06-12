<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Account;
use App\Entity\Transfer;
use App\Enum\TransferStatus;
use App\Exception\TransferException;
use App\Infrastructure\Redis\RedisClientInterface;
use App\Repository\TransferRepository;
use App\Service\IdempotencyService;
use PHPUnit\Framework\TestCase;

final class IdempotencyServiceTest extends TestCase
{
    public function testReturnsCachedResponseForMatchingHash(): void
    {
        $redis = new class implements RedisClientInterface {
            public function get(string $key): ?string
            {
                return json_encode([
                    'id' => '11111111-1111-7111-8111-111111111111',
                    'source_account_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    'destination_account_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                    'amount' => '10.0000',
                    'currency' => 'EUR',
                    'status' => 'COMPLETED',
                    'failure_reason' => null,
                    'created_at' => '2025-01-01T00:00:00+00:00',
                    'completed_at' => '2025-01-01T00:00:01+00:00',
                    'request_hash' => 'hash-1',
                ], JSON_THROW_ON_ERROR);
            }

            public function setex(string $key, int $ttl, string $value): void
            {
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $service = new IdempotencyService(
            $this->createMock(TransferRepository::class),
            $redis,
            3600,
        );

        $response = $service->resolve('key-1', 'hash-1');
        self::assertNotNull($response);
        self::assertSame(TransferStatus::COMPLETED->value, $response->status);
    }

    public function testThrowsConflictWhenHashDiffers(): void
    {
        $source = new Account('LT001', 'EUR', '100.0000');
        $destination = new Account('LT002', 'EUR', '100.0000');
        $transfer = new Transfer($source, $destination, '10.0000', 'key-1', 'hash-1');
        $transfer->markCompleted();

        $repository = $this->createMock(TransferRepository::class);
        $repository->method('findByIdempotencyKey')->willReturn($transfer);

        $redis = new class implements RedisClientInterface {
            public function get(string $key): ?string
            {
                return null;
            }

            public function setex(string $key, int $ttl, string $value): void
            {
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $service = new IdempotencyService($repository, $redis, 3600);

        $this->expectException(TransferException::class);
        $service->resolve('key-1', 'different-hash');
    }
}
