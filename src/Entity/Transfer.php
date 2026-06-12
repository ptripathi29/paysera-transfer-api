<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TransferStatus;
use App\Repository\TransferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ORM\Table(name: 'transfers')]
#[ORM\UniqueConstraint(name: 'uniq_transfers_idempotency_key', columns: ['idempotency_key'])]
#[ORM\Index(name: 'idx_transfers_source_account', columns: ['source_account_id'])]
#[ORM\Index(name: 'idx_transfers_destination_account', columns: ['destination_account_id'])]
#[ORM\Index(name: 'idx_transfers_status', columns: ['status'])]
#[ORM\Index(name: 'idx_transfers_created_at', columns: ['created_at'])]
class Transfer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'source_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $sourceAccount;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'destination_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $destinationAccount;

    #[ORM\Column(type: Types::DECIMAL, precision: 19, scale: 4)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(enumType: TransferStatus::class)]
    private TransferStatus $status = TransferStatus::PENDING;

    #[ORM\Column(name: 'idempotency_key', length: 128)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'request_hash', length: 64)]
    private string $requestHash;

    #[ORM\Column(name: 'failure_reason', length: 500, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(
        Account $sourceAccount,
        Account $destinationAccount,
        string $amount,
        string $idempotencyKey,
        string $requestHash,
    ) {
        $this->id = Uuid::v7();
        $this->sourceAccount = $sourceAccount;
        $this->destinationAccount = $destinationAccount;
        $this->amount = $amount;
        $this->currency = $sourceAccount->getCurrency();
        $this->idempotencyKey = $idempotencyKey;
        $this->requestHash = $requestHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceAccount(): Account
    {
        return $this->sourceAccount;
    }

    public function getDestinationAccount(): Account
    {
        return $this->destinationAccount;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): TransferStatus
    {
        return $this->status;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function markCompleted(): void
    {
        $this->status = TransferStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $reason): void
    {
        $this->status = TransferStatus::FAILED;
        $this->failureReason = $reason;
        $this->completedAt = new \DateTimeImmutable();
    }
}
