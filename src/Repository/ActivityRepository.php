<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Repository;

use Carbon\CarbonImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\RaffleCoreBundle\Entity\Activity;

/**
 * @extends ServiceEntityRepository<Activity>
 */
#[AsRepository(entityClass: Activity::class)]
final class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function save(Activity $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Activity $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<Activity>
     */
    public function findActiveActivities(): array
    {
        $now = CarbonImmutable::now();

        /** @var array<Activity> */
        return $this->createQueryBuilder('a')
            ->where('a.valid = :valid')
            ->andWhere('a.startTime <= :now')
            ->andWhere('a.endTime >= :now')
            ->setParameter('valid', true)
            ->setParameter('now', $now)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Activity>
     */
    public function findUpcomingActivities(int $limit = 10): array
    {
        $now = CarbonImmutable::now();

        /** @var array<Activity> */
        return $this->createQueryBuilder('a')
            ->where('a.valid = :valid')
            ->andWhere('a.startTime > :now')
            ->setParameter('valid', true)
            ->setParameter('now', $now)
            ->orderBy('a.startTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Activity>
     */
    public function findEndedActivities(int $limit = 10): array
    {
        $now = CarbonImmutable::now();

        /** @var array<Activity> */
        return $this->createQueryBuilder('a')
            ->where('a.valid = :valid')
            ->andWhere('a.endTime < :now')
            ->setParameter('valid', true)
            ->setParameter('now', $now)
            ->orderBy('a.endTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}
