<?php

declare(strict_types=1);

namespace App\Enum;

enum AccountStatus: string
{
    case ACTIVE = 'ACTIVE';
    case BLOCKED = 'BLOCKED';
    case CLOSED = 'CLOSED';

    public function allowsTransfers(): bool
    {
        return $this === self::ACTIVE;
    }
}
