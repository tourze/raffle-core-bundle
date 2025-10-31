<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Repository;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Entity\Pool;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Repository\AwardRepository;

/**
 * @internal
 */
#[CoversClass(AwardRepository::class)]
#[RunTestsInSeparateProcesses]
final class AwardRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepository(): AwardRepository
    {
        return self::getService(AwardRepository::class);
    }

    protected function createNewEntity(): Award
    {
        $award = new Award();
        $award->setName('Test Award ' . uniqid());
        $award->setDescription('Test award description');
        $award->setPool($this->createTestPool());
        $award->setSku($this->createTestSku());
        $award->setProbability(100);
        $award->setQuantity(10);
        $award->setAmount(1);
        $award->setValue('50.00');
        $award->setValid(true);
        $award->setNeedConsignee(false);
        $award->setSortNumber(1);

        return $award;
    }

    protected function onSetUp(): void
    {
        // 手动创建测试数据
        $this->loadTestData();
    }

    private function loadTestData(): void
    {
        // 创建测试数据以满足基类测试
        $award1 = $this->createValidAward();
        $this->getRepository()->save($award1, true);

        $award2 = $this->createValidAward();
        $this->getRepository()->save($award2, true);

        $award3 = $this->createValidAward();
        $this->getRepository()->save($award3, true);
    }

    public function testSaveAwardWithoutFlushShouldPersistEntity(): void
    {
        $award = $this->createValidAward();
        $repository = $this->getRepository();

        $repository->save($award, false);

        self::getEntityManager()->flush();
        $this->assertNotNull($award->getId());
    }

    public function testSaveAwardWithFlushShouldPersistImmediately(): void
    {
        $award = $this->createValidAward();
        $repository = $this->getRepository();

        $repository->save($award, true);

        $this->assertNotNull($award->getId());
    }

    public function testRemoveAwardWithoutFlushShouldMarkForDeletion(): void
    {
        $award = $this->createValidAward();
        $repository = $this->getRepository();
        $repository->save($award, true);
        $awardId = $award->getId();

        $repository->remove($award, false);
        self::getEntityManager()->flush();

        $removedAward = $repository->find($awardId);
        $this->assertNull($removedAward);
    }

    public function testRemoveAwardWithFlushShouldDeleteImmediately(): void
    {
        $award = $this->createValidAward();
        $repository = $this->getRepository();
        $repository->save($award, true);
        $awardId = $award->getId();

        $repository->remove($award, true);

        $removedAward = $repository->find($awardId);
        $this->assertNull($removedAward);
    }

    public function testFindAvailableByPoolShouldReturnValidAwards(): void
    {
        $pool = $this->createTestPool();

        $validAward1 = $this->createValidAward();
        $validAward1->setPool($pool);
        $validAward1->setValid(true);
        $validAward1->setQuantity(10);
        $validAward1->setSortNumber(1);

        $validAward2 = $this->createValidAward();
        $validAward2->setPool($pool);
        $validAward2->setValid(true);
        $validAward2->setQuantity(5);
        $validAward2->setSortNumber(2);

        $invalidAward = $this->createValidAward();
        $invalidAward->setPool($pool);
        $invalidAward->setValid(false);
        $invalidAward->setQuantity(10);

        $zeroQuantityAward = $this->createValidAward();
        $zeroQuantityAward->setPool($pool);
        $zeroQuantityAward->setValid(true);
        $zeroQuantityAward->setQuantity(0);

        $this->getRepository()->save($validAward1, true);
        $this->getRepository()->save($validAward2, true);
        $this->getRepository()->save($invalidAward, true);
        $this->getRepository()->save($zeroQuantityAward, true);

        $availableAwards = $this->getRepository()->findAvailableByPool($pool);

        $this->assertCount(2, $availableAwards);
        $this->assertEquals($validAward1->getId(), $availableAwards[0]->getId());
        $this->assertEquals($validAward2->getId(), $availableAwards[1]->getId());
    }

    public function testFindAvailableByActivityShouldReturnValidAwards(): void
    {
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $pool->addActivity($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->flush();

        $validAward = $this->createValidAward();
        $validAward->setPool($pool);
        $validAward->setValid(true);
        $validAward->setQuantity(10);

        $this->getRepository()->save($validAward, true);

        $availableAwards = $this->getRepository()->findAvailableByActivity($activity);

        $this->assertCount(1, $availableAwards);
        $this->assertEquals($validAward->getId(), $availableAwards[0]->getId());
    }

    public function testDecreaseQuantityAtomicallyShouldReturnTrueWhenSuccessful(): void
    {
        $award = $this->createValidAward();
        $award->setQuantity(10);
        $this->getRepository()->save($award, true);

        $result = $this->getRepository()->decreaseQuantityAtomically($award, 3);

        $this->assertTrue($result);

        self::getEntityManager()->refresh($award);
        $this->assertEquals(7, $award->getQuantity());
    }

    public function testDecreaseQuantityAtomicallyShouldReturnFalseWhenInsufficientQuantity(): void
    {
        $award = $this->createValidAward();
        $award->setQuantity(2);
        $this->getRepository()->save($award, true);

        $result = $this->getRepository()->decreaseQuantityAtomically($award, 5);

        $this->assertFalse($result);

        self::getEntityManager()->refresh($award);
        $this->assertEquals(2, $award->getQuantity());
    }

    public function testCountTodayDispatchedByAwardShouldReturnCorrectCount(): void
    {
        $award = $this->createValidAward();
        $this->getRepository()->save($award, true);

        $user = $this->createNormalUser('user@test.com.' . uniqid(), 'password');
        $activity = $this->createTestActivity();

        $todayChance = $this->createTestChance($user, $activity);
        $todayChance->setAward($award);
        $todayChance->setUseTime(CarbonImmutable::today()->addHours(10));

        $yesterdayChance = $this->createTestChance($user, $activity);
        $yesterdayChance->setAward($award);
        $yesterdayChance->setUseTime(CarbonImmutable::yesterday()->addHours(10));

        self::getEntityManager()->persist($todayChance);
        self::getEntityManager()->persist($yesterdayChance);
        self::getEntityManager()->flush();

        $count = $this->getRepository()->countTodayDispatchedByAward($award);

        $this->assertEquals(1, $count);
    }

    public function testCanDispatchTodayShouldReturnTrueWhenNoDayLimit(): void
    {
        $award = $this->createValidAward();
        $award->setDayLimit(null);
        $this->getRepository()->save($award, true);

        $result = $this->getRepository()->canDispatchToday($award);

        $this->assertTrue($result);
    }

    public function testCanDispatchTodayShouldReturnTrueWhenWithinDayLimit(): void
    {
        $award = $this->createValidAward();
        $award->setDayLimit(5);
        $this->getRepository()->save($award, true);

        $user = $this->createNormalUser('user@test.com.' . uniqid(), 'password');
        $activity = $this->createTestActivity();

        for ($i = 0; $i < 3; ++$i) {
            $chance = $this->createTestChance($user, $activity);
            $chance->setAward($award);
            $chance->setUseTime(CarbonImmutable::today()->addHours($i + 1));
            self::getEntityManager()->persist($chance);
        }
        self::getEntityManager()->flush();

        $result = $this->getRepository()->canDispatchToday($award);

        $this->assertTrue($result);
    }

    public function testCanDispatchTodayShouldReturnFalseWhenExceedsDayLimit(): void
    {
        $award = $this->createValidAward();
        $award->setDayLimit(2);
        $this->getRepository()->save($award, true);

        $user = $this->createNormalUser('user@test.com.' . uniqid(), 'password');
        $activity = $this->createTestActivity();

        for ($i = 0; $i < 3; ++$i) {
            $chance = $this->createTestChance($user, $activity);
            $chance->setAward($award);
            $chance->setUseTime(CarbonImmutable::today()->addHours($i + 1));
            self::getEntityManager()->persist($chance);
        }
        self::getEntityManager()->flush();

        $result = $this->getRepository()->canDispatchToday($award);

        $this->assertFalse($result);
    }

    public function testFindEligibleForLotteryByActivityShouldReturnAvailableAwardsWithinDayLimit(): void
    {
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $pool->addActivity($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->flush();

        $eligibleAward = $this->createValidAward();
        $eligibleAward->setPool($pool);
        $eligibleAward->setValid(true);
        $eligibleAward->setQuantity(10);
        $eligibleAward->setDayLimit(5);

        $exceededLimitAward = $this->createValidAward();
        $exceededLimitAward->setPool($pool);
        $exceededLimitAward->setValid(true);
        $exceededLimitAward->setQuantity(10);
        $exceededLimitAward->setDayLimit(1);

        $this->getRepository()->save($eligibleAward, true);
        $this->getRepository()->save($exceededLimitAward, true);

        $user = $this->createNormalUser('user@test.com.' . uniqid(), 'password');
        $chance = $this->createTestChance($user, $activity);
        $chance->setAward($exceededLimitAward);
        $chance->setUseTime(CarbonImmutable::today()->addHours(1));
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $eligibleAwards = $this->getRepository()->findEligibleForLotteryByActivity($activity);

        $this->assertCount(1, $eligibleAwards);
        $this->assertEquals($eligibleAward->getId(), $eligibleAwards[0]->getId());
    }

    private function createValidAward(): Award
    {
        return $this->createNewEntity();
    }

    private function createTestPool(): Pool
    {
        $pool = new Pool();
        $pool->setName('Test Pool ' . uniqid());
        $pool->setDescription('Test pool description');
        $pool->setValid(true);
        $pool->setIsDefault(false);
        $pool->setSortNumber(1);

        self::getEntityManager()->persist($pool);
        self::getEntityManager()->flush();

        return $pool;
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

    private function createTestChance(UserInterface $user, Activity $activity): Chance
    {
        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::INIT);

        return $chance;
    }

    private function createTestSku(): Sku
    {
        // 创建一个最简单的SPU
        $spu = new Spu();
        $spu->setTitle('Test SPU ' . uniqid());
        $spu->setContent('Test SPU for raffle system');
        $spu->setValid(true);

        // 创建一个最简单的SKU
        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setGtin('TEST_SKU_' . uniqid());
        $sku->setUnit('个');
        $sku->setNeedConsignee(true);
        $sku->setThumbs([['url' => '/images/test-sku-thumb.png']]);

        // 先保存SPU再保存SKU
        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        return $sku;
    }
}
