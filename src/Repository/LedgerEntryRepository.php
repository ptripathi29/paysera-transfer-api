<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LedgerEntry;
use App\Entity\Transfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LedgerEntry>
 */
class LedgerEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LedgerEntry::class);
    }

    /**
     * @return LedgerEntry[]
     */
    public function findByTransfer(Transfer $transfer): array
    {
        return $this->findBy(['transfer' => $transfer], ['createdAt' => 'ASC']);
    }
}
