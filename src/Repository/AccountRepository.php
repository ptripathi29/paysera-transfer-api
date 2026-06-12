<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findByUuid(Uuid $id): ?Account
    {
        return $this->find($id);
    }

    /**
     * Acquires a pessimistic write lock on the account row.
     * Must be called within an active database transaction.
     */
    public function findForUpdate(Uuid $id): ?Account
    {
        return $this->createQueryBuilder('a')
            ->where('a.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * Lock accounts in deterministic UUID order to prevent deadlocks.
     *
     * @return array{0: Account, 1: Account}
     */
    public function findPairForUpdate(Uuid $sourceId, Uuid $destinationId): array
    {
        $ids = [$sourceId->toRfc4122(), $destinationId->toRfc4122()];
        sort($ids, SORT_STRING);

        $first = $this->findForUpdate(Uuid::fromString($ids[0]));
        $second = $this->findForUpdate(Uuid::fromString($ids[1]));

        if ($first === null || $second === null) {
            return [$first, $second];
        }

        if ($sourceId->equals($first->getId())) {
            return [$first, $second];
        }

        return [$second, $first];
    }
}
