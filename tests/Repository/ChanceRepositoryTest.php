<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Repository;

use Carbon\CarbonImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;

/**
 * @internal
 */
#[CoversClass(ChanceRepository::class)]
#[RunTestsInSeparateProcesses]
final class ChanceRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepository(): ChanceRepository
    {
        return self::getService(ChanceRepository::class);
    }

    protected function createNewEntity(): Chance
    {
        $chance = new Chance();
        $chance->setUser($this->createNormalUser('default@test.com.' . uniqid(), 'password'));
        $chance->setActivity($this->createTestActivity());
        $chance->setStatus(ChanceStatusEnum::INIT);

        return $chance;
    }

    protected function onSetUp(): void
    {
        // 手动创建测试数据
        $this->loadTestData();
    }

    private function loadTestData(): void
    {
        // 创建测试数据以满足基类测试
        $chance1 = $this->createValidChance();
        $this->getRepository()->save($chance1, true);

        $chance2 = $this->createValidChance();
        $this->getRepository()->save($chance2, true);

        $chance3 = $this->createValidChance();
        $this->getRepository()->save($chance3, true);
    }

    public function testOptimisticLockVersionShouldBeInitializedOnPersist(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();

        // 新实体版本号应该是null
        $this->assertNull($chance->getLockVersion());

        $repository->save($chance, true);

        // 保存后版本号应该被初始化
        $this->assertNotNull($chance->getLockVersion());
        $this->assertEquals(1, $chance->getLockVersion());
    }

    public function testFindWithOptimisticLockWhenVersionMismatchesShouldThrowExceptionOnFlush(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();
        $repository->save($chance, true);

        $chanceId = $chance->getId();
        $this->assertNotNull($chanceId);

        // 使用 DBAL 直接更新数据库中的版本号，模拟外部更新
        $em = self::getEntityManager();
        $connection = $em->getConnection();
        $connection->executeStatement(
            'UPDATE raffle_chance SET lock_version = lock_version + 1 WHERE id = ?',
            [$chanceId]
        );

        // 修改实体属性
        $chance->setStatus(ChanceStatusEnum::WINNING);

        $this->expectException(OptimisticLockException::class);

        // flush() 应该因版本冲突而抛出异常
        $em->flush();
    }

    public function testFindWithPessimisticWriteLockShouldReturnEntityAndLockRow(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();
        $repository->save($chance, true);
        $chanceId = $chance->getId();

        // 悲观锁需要在事务中执行
        $em = self::getEntityManager();
        $em->beginTransaction();

        try {
            $lockedChance = $em->find(Chance::class, $chanceId, LockMode::PESSIMISTIC_WRITE);

            $this->assertNotNull($lockedChance);
            $this->assertEquals($chanceId, $lockedChance->getId());
            $this->assertEquals($chance->getStatus(), $lockedChance->getStatus());

            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }
    }

    public function testSaveChanceWithoutFlushShouldPersistEntity(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();

        $repository->save($chance, false);

        self::getEntityManager()->flush();
        $this->assertNotNull($chance->getId());
    }

    public function testSaveChanceWithFlushShouldPersistImmediately(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();

        $repository->save($chance, true);

        $this->assertNotNull($chance->getId());
    }

    public function testRemoveChanceWithoutFlushShouldMarkForDeletion(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();
        $repository->save($chance, true);
        $chanceId = $chance->getId();

        $repository->remove($chance, false);
        self::getEntityManager()->flush();

        $removedChance = $repository->find($chanceId);
        $this->assertNull($removedChance);
    }

    public function testRemoveChanceWithFlushShouldDeleteImmediately(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();
        $repository->save($chance, true);
        $chanceId = $chance->getId();

        $repository->remove($chance, true);

        $removedChance = $repository->find($chanceId);
        $this->assertNull($removedChance);
    }

    public function testSaveWithLockShouldSucceedWithCorrectVersion(): void
    {
        $chance = $this->createValidChance();
        $repository = $this->getRepository();
        $repository->save($chance, true);

        $originalVersion = $chance->getLockVersion();

        // 使用正确的版本号进行锁定保存
        $chance->setStatus(ChanceStatusEnum::WINNING);
        $repository->saveWithLock($chance, true);

        // 版本号应该递增
        $this->assertGreaterThan($originalVersion, $chance->getLockVersion());
    }

    public function testFindByUserAndActivityShouldReturnUserSpecificChances(): void
    {
        $user1 = $this->createNormalUser('user1@test.com.' . uniqid(), 'password');
        $user2 = $this->createNormalUser('user2@test.com.' . uniqid(), 'password');
        $activity = $this->createTestActivity();

        $chance1 = $this->createValidChance();
        $chance1->setUser($user1);
        $chance1->setActivity($activity);
        $chance1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $chance2 = $this->createValidChance();
        $chance2->setUser($user2);
        $chance2->setActivity($activity);
        $chance2->setCreateTime(new \DateTimeImmutable('2024-01-01 11:00:00'));

        $chance3 = $this->createValidChance();
        $chance3->setUser($user1);
        $chance3->setActivity($activity);
        $chance3->setCreateTime(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $repository = $this->getRepository();
        $repository->save($chance1, true);
        $repository->save($chance2, true);
        $repository->save($chance3, true);

        $user1Chances = $repository->findByUserAndActivity($user1, $activity);

        $this->assertCount(2, $user1Chances);

        // 应该按创建时间倒序返回，所以最后创建的在前面
        $resultIds = array_map(fn ($chance) => $chance->getId(), $user1Chances);
        $expectedIds = [$chance3->getId(), $chance1->getId()];

        $this->assertEquals($expectedIds, $resultIds);
    }

    public function testFindWinningChancesByUserShouldReturnOnlyWinningChances(): void
    {
        $user = $this->createNormalUser('user@test.com.' . uniqid(), 'password');

        $winningChance = $this->createValidChance();
        $winningChance->setUser($user);
        $winningChance->setStatus(ChanceStatusEnum::WINNING);
        $winningChance->setUseTime(CarbonImmutable::now());

        $initChance = $this->createValidChance();
        $initChance->setUser($user);
        $initChance->setStatus(ChanceStatusEnum::INIT);

        $repository = $this->getRepository();
        $repository->save($winningChance, true);
        $repository->save($initChance, true);

        $winningChances = $repository->findWinningChancesByUser($user, 10);

        $this->assertCount(1, $winningChances);
        $this->assertEquals($winningChance->getId(), $winningChances[0]->getId());
        $this->assertEquals(ChanceStatusEnum::WINNING, $winningChances[0]->getStatus());
    }

    public function testFindWinningChancesByUserWithLimitShouldRespectMaxResults(): void
    {
        $user = $this->createNormalUser('user@test.com.' . uniqid(), 'password');

        $repository = $this->getRepository();
        for ($i = 1; $i <= 5; ++$i) {
            $chance = $this->createValidChance();
            $chance->setUser($user);
            $chance->setStatus(ChanceStatusEnum::WINNING);
            $chance->setUseTime(CarbonImmutable::now()->addMinutes($i));
            $repository->save($chance, true);
        }

        $winningChances = $repository->findWinningChancesByUser($user, 3);

        $this->assertCount(3, $winningChances);
    }

    public function testFindExpiredChancesShouldReturnOldUnusedChances(): void
    {
        $expiredChance1 = $this->createValidChance();
        $expiredChance1->setStatus(ChanceStatusEnum::INIT);
        $expiredChance1->setUseTime(CarbonImmutable::now()->subDays(8));

        $expiredChance2 = $this->createValidChance();
        $expiredChance2->setStatus(ChanceStatusEnum::WINNING);
        $expiredChance2->setUseTime(CarbonImmutable::now()->subDays(10));

        $recentChance = $this->createValidChance();
        $recentChance->setStatus(ChanceStatusEnum::INIT);
        $recentChance->setUseTime(CarbonImmutable::now()->subDays(3));

        $nullUseTimeChance = $this->createValidChance();
        $nullUseTimeChance->setStatus(ChanceStatusEnum::INIT);

        $repository = $this->getRepository();
        $repository->save($expiredChance1, true);
        $repository->save($expiredChance2, true);
        $repository->save($recentChance, true);
        $repository->save($nullUseTimeChance, true);

        $expiredChances = $repository->findExpiredChances();

        $this->assertCount(2, $expiredChances);

        $expiredIds = array_map(fn (Chance $chance) => $chance->getId(), $expiredChances);
        $this->assertContains($expiredChance1->getId(), $expiredIds);
        $this->assertContains($expiredChance2->getId(), $expiredIds);
    }

    public function testCountUserChancesInActivityShouldReturnCorrectCount(): void
    {
        $user = $this->createNormalUser('user@test.com.' . uniqid(), 'password');
        $activity1 = $this->createTestActivity();
        $activity2 = $this->createTestActivity();

        $chance1 = $this->createValidChance();
        $chance1->setUser($user);
        $chance1->setActivity($activity1);

        $chance2 = $this->createValidChance();
        $chance2->setUser($user);
        $chance2->setActivity($activity1);

        $chance3 = $this->createValidChance();
        $chance3->setUser($user);
        $chance3->setActivity($activity2);

        $repository = $this->getRepository();
        $repository->save($chance1, true);
        $repository->save($chance2, true);
        $repository->save($chance3, true);

        $count = $repository->countUserChancesInActivity($user, $activity1);

        $this->assertEquals(2, $count);
    }

    public function testCountWinningChancesByActivityShouldReturnOnlyWinningCount(): void
    {
        $activity = $this->createTestActivity();

        $winningChance1 = $this->createValidChance();
        $winningChance1->setActivity($activity);
        $winningChance1->setStatus(ChanceStatusEnum::WINNING);

        $winningChance2 = $this->createValidChance();
        $winningChance2->setActivity($activity);
        $winningChance2->setStatus(ChanceStatusEnum::WINNING);

        $initChance = $this->createValidChance();
        $initChance->setActivity($activity);
        $initChance->setStatus(ChanceStatusEnum::INIT);

        $repository = $this->getRepository();
        $repository->save($winningChance1, true);
        $repository->save($winningChance2, true);
        $repository->save($initChance, true);

        $count = $repository->countWinningChancesByActivity($activity);

        $this->assertEquals(2, $count);
    }

    private function createValidChance(): Chance
    {
        return $this->createNewEntity();
    }

    private function createTestActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('Test Activity ' . uniqid());
        $activity->setDescription('Test Description');
        $activity->setStartTime(CarbonImmutable::now());
        $activity->setEndTime(CarbonImmutable::now()->addDay());
        $activity->setValid(true);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        return $activity;
    }
}
