<?php

declare(strict_types=1);

namespace App\Enum;

enum TransferStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
