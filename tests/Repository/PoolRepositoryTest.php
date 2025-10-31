<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Repository;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Pool;
use Tourze\RaffleCoreBundle\Repository\PoolRepository;

/**
 * @internal
 */
#[CoversClass(PoolRepository::class)]
#[RunTestsInSeparateProcesses]
final class PoolRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepository(): PoolRepository
    {
        return self::getService(PoolRepository::class);
    }

    protected function createNewEntity(): Pool
    {
        $pool = new Pool();
        $pool->setName('Test Pool ' . uniqid());
        $pool->setDescription('Test pool description');
        $pool->setValid(true);
        $pool->setIsDefault(false);
        $pool->setSortNumber(1);

        return $pool;
    }

    protected function onSetUp(): void
    {
        // 手动创建测试数据
        $this->loadTestData();
    }

    private function loadTestData(): void
    {
        // 创建测试数据以满足基类测试
        $pool1 = $this->createValidPool();
        $this->getRepository()->save($pool1, true);

        $pool2 = $this->createValidPool();
        $this->getRepository()->save($pool2, true);

        $pool3 = $this->createValidPool();
        $this->getRepository()->save($pool3, true);
    }

    public function testSavePoolWithoutFlushShouldPersistEntity(): void
    {
        $pool = $this->createValidPool();
        $repository = $this->getRepository();

        $repository->save($pool, false);

        self::getEntityManager()->flush();
        $this->assertNotNull($pool->getId());
    }

    public function testSavePoolWithFlushShouldPersistImmediately(): void
    {
        $pool = $this->createValidPool();
        $repository = $this->getRepository();

        $repository->save($pool, true);

        $this->assertNotNull($pool->getId());
    }

    public function testRemovePoolWithoutFlushShouldMarkForDeletion(): void
    {
        $pool = $this->createValidPool();
        $repository = $this->getRepository();
        $repository->save($pool, true);
        $poolId = $pool->getId();

        $repository->remove($pool, false);
        self::getEntityManager()->flush();

        $removedPool = $repository->find($poolId);
        $this->assertNull($removedPool);
    }

    public function testRemovePoolWithFlushShouldDeleteImmediately(): void
    {
        $pool = $this->createValidPool();
        $repository = $this->getRepository();
        $repository->save($pool, true);
        $poolId = $pool->getId();

        $repository->remove($pool, true);

        $removedPool = $repository->find($poolId);
        $this->assertNull($removedPool);
    }

    public function testFindByActivityShouldReturnValidPools(): void
    {
        $activity = $this->createTestActivity();

        $validPool1 = $this->createValidPool();
        $validPool1->setValid(true);
        $validPool1->setSortNumber(1);
        // 先保存Pool，然后建立关系
        $this->getRepository()->save($validPool1, true);
        $validPool1->addActivity($activity);

        $validPool2 = $this->createValidPool();
        $validPool2->setValid(true);
        $validPool2->setSortNumber(2);
        $this->getRepository()->save($validPool2, true);
        $validPool2->addActivity($activity);

        $invalidPool = $this->createValidPool();
        $invalidPool->setValid(false);
        $this->getRepository()->save($invalidPool, true);
        $invalidPool->addActivity($activity);

        // 刷新关系到数据库
        self::getEntityManager()->flush();

        $availablePools = $this->getRepository()->findByActivity($activity);

        $this->assertCount(2, $availablePools);
        $this->assertEquals($validPool1->getId(), $availablePools[0]->getId());
        $this->assertEquals($validPool2->getId(), $availablePools[1]->getId());
    }

    public function testFindDefaultPoolsShouldReturnOnlyDefaultPools(): void
    {
        // 清理现有的默认池，确保测试的隔离性
        $existingDefaultPools = $this->getRepository()->findDefaultPools();
        foreach ($existingDefaultPools as $pool) {
            $this->getRepository()->remove($pool, true);
        }

        $defaultPool1 = $this->createValidPool();
        $defaultPool1->setValid(true);
        $defaultPool1->setIsDefault(true);
        $defaultPool1->setSortNumber(1);

        $defaultPool2 = $this->createValidPool();
        $defaultPool2->setValid(true);
        $defaultPool2->setIsDefault(true);
        $defaultPool2->setSortNumber(2);

        $nonDefaultPool = $this->createValidPool();
        $nonDefaultPool->setValid(true);
        $nonDefaultPool->setIsDefault(false);

        $this->getRepository()->save($defaultPool1, true);
        $this->getRepository()->save($defaultPool2, true);
        $this->getRepository()->save($nonDefaultPool, true);

        $defaultPools = $this->getRepository()->findDefaultPools();

        $this->assertCount(2, $defaultPools);
        $this->assertEquals($defaultPool1->getId(), $defaultPools[0]->getId());
        $this->assertEquals($defaultPool2->getId(), $defaultPools[1]->getId());
        $this->assertTrue($defaultPools[0]->isDefault());
        $this->assertTrue($defaultPools[1]->isDefault());
    }

    public function testFindValidPoolsShouldReturnValidPoolsOnly(): void
    {
        // 清理现有的有效池，确保测试的隔离性
        $existingValidPools = $this->getRepository()->findValidPools();
        foreach ($existingValidPools as $pool) {
            $this->getRepository()->remove($pool, true);
        }

        $validPool1 = $this->createValidPool();
        $validPool1->setValid(true);
        $validPool1->setSortNumber(1);

        $validPool2 = $this->createValidPool();
        $validPool2->setValid(true);
        $validPool2->setSortNumber(2);

        $invalidPool = $this->createValidPool();
        $invalidPool->setValid(false);

        $this->getRepository()->save($validPool1, true);
        $this->getRepository()->save($validPool2, true);
        $this->getRepository()->save($invalidPool, true);

        $validPools = $this->getRepository()->findValidPools();

        $this->assertCount(2, $validPools);
        $this->assertEquals($validPool1->getId(), $validPools[0]->getId());
        $this->assertEquals($validPool2->getId(), $validPools[1]->getId());
        $this->assertTrue($validPools[0]->isValid());
        $this->assertTrue($validPools[1]->isValid());
    }

    private function createValidPool(): Pool
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
