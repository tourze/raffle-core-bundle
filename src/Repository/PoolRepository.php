<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Pool;

/**
 * @extends ServiceEntityRepository<Pool>
 */
#[AsRepository(entityClass: Pool::class)]
class PoolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pool::class);
    }

    public function save(Pool $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Pool $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<Pool>
     */
    public function findValidPools(): array
    {
        /** @var array<Pool> */
        return $this->createQueryBuilder('p')
            ->where('p.valid = :valid')
            ->setParameter('valid', true)
            ->orderBy('p.sortNumber', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Pool>
     */
    public function findByActivity(Activity $activity): array
    {
        /** @var array<Pool> */
        return $this->createQueryBuilder('p')
            ->join('p.activities', 'a')
            ->where('a = :activity')
            ->andWhere('p.valid = :valid')
            ->setParameter('activity', $activity)
            ->setParameter('valid', true)
            ->orderBy('p.sortNumber', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Pool>
     */
    public function findDefaultPools(): array
    {
        /** @var array<Pool> */
        return $this->createQueryBuilder('p')
            ->where('p.isDefault = :isDefault')
            ->andWhere('p.valid = :valid')
            ->setParameter('isDefault', true)
            ->setParameter('valid', true)
            ->orderBy('p.sortNumber', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
