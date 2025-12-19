<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Repository;

use Carbon\CarbonImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Pool;

/**
 * @extends ServiceEntityRepository<Award>
 */
#[AsRepository(entityClass: Award::class)]
final class AwardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Award::class);
    }

    public function save(Award $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Award $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<Award>
     */
    public function findAvailableByPool(Pool $pool): array
    {
        /** @var array<Award> */
        return $this->createQueryBuilder('a')
            ->where('a.pool = :pool')
            ->andWhere('a.valid = :valid')
            ->andWhere('a.quantity > 0')
            ->setParameter('pool', $pool)
            ->setParameter('valid', true)
            ->orderBy('a.sortNumber', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Award>
     */
    public function findAvailableByActivity(Activity $activity): array
    {
        /** @var array<Award> */
        return $this->createQueryBuilder('a')
            ->join('a.pool', 'p')
            ->join('p.activities', 'act')
            ->where('act = :activity')
            ->andWhere('a.valid = :valid')
            ->andWhere('p.valid = :poolValid')
            ->andWhere('a.quantity > 0')
            ->setParameter('activity', $activity)
            ->setParameter('valid', true)
            ->setParameter('poolValid', true)
            ->orderBy('p.sortNumber', 'ASC')
            ->addOrderBy('a.sortNumber', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function decreaseQuantityAtomically(Award $award, int $amount): bool
    {
        $query = $this->getEntityManager()->createQuery(
            'UPDATE ' . Award::class . ' a SET a.quantity = a.quantity - :amount WHERE a.id = :id AND a.quantity >= :amount'
        );

        $query->setParameter('amount', $amount);
        $query->setParameter('id', $award->getId());

        return $query->execute() > 0;
    }

    public function countTodayDispatchedByAward(Award $award): int
    {
        $today = CarbonImmutable::today();
        $tomorrow = $today->addDay();

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(c.id)')
            ->join('a.chances', 'c')
            ->where('c.award = :award')
            ->andWhere('c.useTime >= :today')
            ->andWhere('c.useTime < :tomorrow')
            ->setParameter('award', $award)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function canDispatchToday(Award $award): bool
    {
        if (null === $award->getDayLimit()) {
            return true;
        }

        $todayCount = $this->countTodayDispatchedByAward($award);

        return $todayCount < $award->getDayLimit();
    }

    /**
     * @return Award[]
     */
    public function findEligibleForLotteryByActivity(Activity $activity): array
    {
        $availableAwards = $this->findAvailableByActivity($activity);

        return array_filter($availableAwards, function (Award $award) {
            return $this->canDispatchToday($award);
        });
    }
}
