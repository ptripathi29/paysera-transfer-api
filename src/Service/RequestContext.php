<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Uid\Uuid;

final class RequestContext
{
    private string $requestId;
    private ?string $correlationId = null;

    public function __construct()
    {
        $this->requestId = Uuid::v4()->toRfc4122();
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId ?? $this->requestId;
    }

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }
}
