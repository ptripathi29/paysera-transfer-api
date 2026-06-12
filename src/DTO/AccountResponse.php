<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Account;

final class AccountResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $accountNumber,
        public readonly string $balance,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    public static function fromEntity(Account $account): self
    {
        return new self(
            id: $account->getId()->toRfc4122(),
            accountNumber: $account->getAccountNumber(),
            balance: $account->getBalance(),
            currency: $account->getCurrency(),
            status: $account->getStatus()->value,
            createdAt: $account->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $account->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->accountNumber,
            'balance' => $this->balance,
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
