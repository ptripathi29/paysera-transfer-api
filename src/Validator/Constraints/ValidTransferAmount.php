<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ValidTransferAmount extends Constraint
{
    public string $message = 'Transfer amount must be a positive decimal with up to 4 fractional digits.';
}
