<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidTransferAmountValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidTransferAmount) {
            throw new UnexpectedTypeException($constraint, ValidTransferAmount::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) || !preg_match('/^\d+(\.\d{1,4})?$/', $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if (bccomp($value, '0', 4) <= 0) {
            $this->context->buildViolation('Transfer amount must be greater than zero.')->addViolation();
        }
    }
}
