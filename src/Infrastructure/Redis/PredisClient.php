<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Predis\Client;
use Psr\Log\LoggerInterface;

final class PredisClient implements RedisClientInterface
{
    private ?Client $client = null;
    private bool $available = true;

    public function __construct(
        private readonly string $redisUrl,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(string $key): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $value = $this->getClient()->get($key);

            return is_string($value) ? $value : null;
        } catch (\Throwable $e) {
            $this->markUnavailable($e);

            return null;
        }
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        try {
            $this->getClient()->setex($key, $ttl, $value);
        } catch (\Throwable $e) {
            $this->markUnavailable($e);
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client($this->redisUrl);
        }

        return $this->client;
    }

    private function markUnavailable(\Throwable $e): void
    {
        $this->available = false;
        $this->logger->warning('Redis unavailable, falling back to database', [
            'error' => $e->getMessage(),
        ]);
    }
}
