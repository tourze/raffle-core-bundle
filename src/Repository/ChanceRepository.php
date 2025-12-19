<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;

/**
 * @extends ServiceEntityRepository<Chance>
 */
#[AsRepository(entityClass: Chance::class)]
final class ChanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chance::class);
    }

    public function save(Chance $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Chance $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function saveWithLock(Chance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->lock($entity, LockMode::OPTIMISTIC, $entity->getLockVersion());
        $this->save($entity, $flush);
    }

    /**
     * @return array<Chance>
     */
    public function findByUserAndActivity(UserInterface $user, Activity $activity): array
    {
        /** @var array<Chance> */
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.activity = :activity')
            ->setParameter('user', $user)
            ->setParameter('activity', $activity)
            ->orderBy('c.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Chance>
     */
    public function findWinningChancesByUser(UserInterface $user, int $limit = 10): array
    {
        /** @var array<Chance> */
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ChanceStatusEnum::WINNING)
            ->orderBy('c.useTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Chance>
     */
    public function findExpiredChances(): array
    {
        /** @var array<Chance> */
        return $this->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->andWhere('c.useTime IS NOT NULL')
            ->andWhere('c.useTime < :expiredTime')
            ->setParameter('statuses', [ChanceStatusEnum::INIT, ChanceStatusEnum::WINNING])
            ->setParameter('expiredTime', new \DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getResult()
        ;
    }

    public function countUserChancesInActivity(UserInterface $user, Activity $activity): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.user = :user')
            ->andWhere('c.activity = :activity')
            ->setParameter('user', $user)
            ->setParameter('activity', $activity)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countWinningChancesByActivity(Activity $activity): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.activity = :activity')
            ->andWhere('c.status = :status')
            ->setParameter('activity', $activity)
            ->setParameter('status', ChanceStatusEnum::WINNING)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
