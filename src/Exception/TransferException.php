<?php

declare(strict_types=1);

namespace App\Exception;

class TransferException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function accountNotFound(string $accountId): self
    {
        return new self(sprintf('Account %s not found.', $accountId), 'ACCOUNT_NOT_FOUND', 404);
    }

    public static function accountNotActive(string $status): self
    {
        return new self(sprintf('Account is not active (status: %s).', $status), 'ACCOUNT_NOT_ACTIVE', 422);
    }

    public static function sameAccount(): self
    {
        return new self('Source and destination accounts must differ.', 'SAME_ACCOUNT', 422);
    }

    public static function insufficientFunds(): self
    {
        return new self('Insufficient funds for transfer.', 'INSUFFICIENT_FUNDS', 422);
    }

    public static function currencyMismatch(): self
    {
        return new self('Source and destination accounts must share the same currency.', 'CURRENCY_MISMATCH', 422);
    }

    public static function amountExceedsLimit(string $limit): self
    {
        return new self(sprintf('Transfer amount exceeds maximum limit of %s.', $limit), 'AMOUNT_EXCEEDS_LIMIT', 422);
    }

    public static function idempotencyConflict(): self
    {
        return new self(
            'Idempotency key was already used with a different request payload.',
            'IDEMPOTENCY_CONFLICT',
            409,
        );
    }

    public static function missingIdempotencyKey(): self
    {
        return new self('Idempotency-Key header is required.', 'MISSING_IDEMPOTENCY_KEY', 400);
    }

    public static function transferNotFound(string $transferId): self
    {
        return new self(sprintf('Transfer %s not found.', $transferId), 'TRANSFER_NOT_FOUND', 404);
    }
}
