<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccountStatus;
use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(name: 'idx_accounts_account_number', columns: ['account_number'])]
#[ORM\Index(name: 'idx_accounts_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'account_number', length: 34, unique: true)]
    private string $accountNumber;

    #[ORM\Column(type: Types::DECIMAL, precision: 19, scale: 4)]
    private string $balance = '0.0000';

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(enumType: AccountStatus::class)]
    private AccountStatus $status = AccountStatus::ACTIVE;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function __construct(string $accountNumber, string $currency, string $initialBalance = '0.0000')
    {
        $this->id = Uuid::v7();
        $this->accountNumber = $accountNumber;
        $this->currency = strtoupper($currency);
        $this->balance = $initialBalance;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): AccountStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function debit(string $amount): void
    {
        $newBalance = bcsub($this->balance, $amount, 4);
        if (bccomp($newBalance, '0', 4) < 0) {
            throw new \DomainException('Insufficient funds');
        }
        $this->balance = $newBalance;
        $this->touch();
    }

    public function credit(string $amount): void
    {
        $this->balance = bcadd($this->balance, $amount, 4);
        $this->touch();
    }

    public function hasSufficientFunds(string $amount): bool
    {
        return bccomp($this->balance, $amount, 4) >= 0;
    }

    public function block(): void
    {
        $this->status = AccountStatus::BLOCKED;
        $this->touch();
    }

    public function close(): void
    {
        $this->status = AccountStatus::CLOSED;
        $this->touch();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
