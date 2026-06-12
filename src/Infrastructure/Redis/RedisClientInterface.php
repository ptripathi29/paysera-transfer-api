<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

interface RedisClientInterface
{
    public function get(string $key): ?string;

    public function setex(string $key, int $ttl, string $value): void;

    public function isAvailable(): bool;
}
