<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Transfer>
 */
class TransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transfer::class);
    }

    public function findByUuid(Uuid $id): ?Transfer
    {
        return $this->find($id);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Transfer
    {
        return $this->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }
}
