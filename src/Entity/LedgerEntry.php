<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LedgerEntryType;
use App\Repository\LedgerEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LedgerEntryRepository::class)]
#[ORM\Table(name: 'ledger_entries')]
#[ORM\Index(name: 'idx_ledger_transfer', columns: ['transfer_id'])]
#[ORM\Index(name: 'idx_ledger_account', columns: ['account_id'])]
#[ORM\Index(name: 'idx_ledger_created_at', columns: ['created_at'])]
class LedgerEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Transfer::class)]
    #[ORM\JoinColumn(name: 'transfer_id', referencedColumnName: 'id', nullable: false)]
    private Transfer $transfer;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false)]
    private Account $account;

    #[ORM\Column(enumType: LedgerEntryType::class)]
    private LedgerEntryType $type;

    #[ORM\Column(type: Types::DECIMAL, precision: 19, scale: 4)]
    private string $amount;

    #[ORM\Column(name: 'balance_after', type: Types::DECIMAL, precision: 19, scale: 4)]
    private string $balanceAfter;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Transfer $transfer,
        Account $account,
        LedgerEntryType $type,
        string $amount,
        string $balanceAfter,
    ) {
        $this->id = Uuid::v7();
        $this->transfer = $transfer;
        $this->account = $account;
        $this->type = $type;
        $this->amount = $amount;
        $this->balanceAfter = $balanceAfter;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTransfer(): Transfer
    {
        return $this->transfer;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getType(): LedgerEntryType
    {
        return $this->type;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getBalanceAfter(): string
    {
        return $this->balanceAfter;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
