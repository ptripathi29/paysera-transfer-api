<?php

declare(strict_types=1);

namespace App\Enum;

enum LedgerEntryType: string
{
    case DEBIT = 'DEBIT';
    case CREDIT = 'CREDIT';
}
