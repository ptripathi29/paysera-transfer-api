<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Transfer;

final class TransferResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $sourceAccountId,
        public readonly string $destinationAccountId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $failureReason,
        public readonly string $createdAt,
        public readonly ?string $completedAt,
    ) {
    }

    public static function fromEntity(Transfer $transfer): self
    {
        return new self(
            id: $transfer->getId()->toRfc4122(),
            sourceAccountId: $transfer->getSourceAccount()->getId()->toRfc4122(),
            destinationAccountId: $transfer->getDestinationAccount()->getId()->toRfc4122(),
            amount: $transfer->getAmount(),
            currency: $transfer->getCurrency(),
            status: $transfer->getStatus()->value,
            failureReason: $transfer->getFailureReason(),
            createdAt: $transfer->getCreatedAt()->format(\DateTimeInterface::ATOM),
            completedAt: $transfer->getCompletedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_account_id' => $this->sourceAccountId,
            'destination_account_id' => $this->destinationAccountId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'failure_reason' => $this->failureReason,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
