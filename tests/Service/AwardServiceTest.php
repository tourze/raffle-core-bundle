<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Pool;
use Tourze\RaffleCoreBundle\Service\AwardService;

/**
 * @internal
 */
#[CoversClass(AwardService::class)]
#[RunTestsInSeparateProcesses]
final class AwardServiceTest extends AbstractIntegrationTestCase
{
    private AwardService $awardService;

    protected function onSetUp(): void
    {
        $this->awardService = self::getService(AwardService::class);
    }

    public function testGetAvailableAwardsByActivityShouldReturnAwardsFromRepository(): void
    {
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award1 = $this->createAwardWithQuantity('奖品1', 10, $pool);
        $award2 = $this->createAwardWithQuantity('奖品2', 5, $pool);
        $invalidAward = $this->createAwardWithQuantity('无效奖品', 3, $pool);
        $invalidAward->setValid(false);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award1);
        self::getEntityManager()->persist($award2);
        self::getEntityManager()->persist($invalidAward);
        self::getEntityManager()->flush();

        $result = $this->awardService->getAvailableAwardsByActivity($activity);

        $this->assertCount(2, $result);
        $names = array_map(fn ($award) => $award->getName(), $result);
        $this->assertContains('奖品1', $names);
        $this->assertContains('奖品2', $names);
        $this->assertNotContains('无效奖品', $names);
    }

    public function testGetEligibleAwardsForLotteryShouldReturnEligibleAwards(): void
    {
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createAwardWithQuantity('可用奖品', 5, $pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->getEligibleAwardsForLottery($activity);

        $this->assertCount(1, $result);
        $this->assertSame('可用奖品', $result[0]->getName());
    }

    public function testCheckAwardStockShouldReturnTrueWhenQuantityGreaterThanZero(): void
    {
        $award = $this->createAwardWithQuantity('奖品', 5, $this->createTestPool());

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->checkAwardStock($award);

        $this->assertTrue($result);
    }

    public function testCheckAwardStockShouldReturnFalseWhenQuantityIsZero(): void
    {
        $award = $this->createAwardWithQuantity('无库存奖品', 0, $this->createTestPool());

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->checkAwardStock($award);

        $this->assertFalse($result);
    }

    public function testIsAwardEligibleShouldReturnTrueWhenAllConditionsMet(): void
    {
        $award = $this->createAwardWithQuantity('有库存奖品', 10, $this->createTestPool());
        $award->setValid(true);

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->isAwardEligible($award);

        $this->assertTrue($result);
    }

    public function testIsAwardEligibleShouldReturnFalseWhenAwardIsInvalid(): void
    {
        $award = $this->createAwardWithQuantity('有库存奖品', 10, $this->createTestPool());
        $award->setValid(false);

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->isAwardEligible($award);

        $this->assertFalse($result);
    }

    public function testIsAwardEligibleShouldReturnFalseWhenNoStock(): void
    {
        $award = $this->createAwardWithQuantity('无库存奖品', 0, $this->createTestPool());
        $award->setValid(true);

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->isAwardEligible($award);

        $this->assertFalse($result);
    }

    public function testIsAwardEligibleShouldReturnFalseWhenDailyLimitReached(): void
    {
        $award = $this->createAwardWithQuantity('有限量奖品', 10, $this->createTestPool());
        $award->setValid(true);
        $award->setDayLimit(0); // 设置为0，表示今日限额已满

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->isAwardEligible($award);

        $this->assertFalse($result);
    }

    public function testCheckDailyLimitShouldReturnTrueWhenLimitNotReached(): void
    {
        $award = $this->createAwardWithQuantity('有限量奖品', 10, $this->createTestPool());
        $award->setValid(true);
        $award->setDayLimit(100); // 设置较高的日限额

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->checkDailyLimit($award);

        $this->assertTrue($result);
    }

    public function testDecreaseAwardStockShouldReturnFalseWhenNoStock(): void
    {
        $award = $this->createAwardWithQuantity('无库存奖品', 0, $this->createTestPool());

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->decreaseAwardStock($award);

        $this->assertFalse($result);
    }

    public function testDecreaseAwardStockShouldReturnTrueWhenStockAvailable(): void
    {
        $award = $this->createAwardWithQuantity('有库存奖品', 5, $this->createTestPool());

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->decreaseAwardStock($award);

        $this->assertTrue($result);

        // 验证库存已减少
        self::getEntityManager()->refresh($award);
        $this->assertEquals(4, $award->getQuantity());
    }

    public function testDecreaseAwardStockShouldSupportCustomAmount(): void
    {
        $award = $this->createAwardWithQuantity('奖品', 10, $this->createTestPool());

        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->awardService->decreaseAwardStock($award, 3);

        $this->assertTrue($result);

        // 验证库存已减少指定数量
        self::getEntityManager()->refresh($award);
        $this->assertEquals(7, $award->getQuantity());
    }

    private function createTestActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('测试活动');
        $activity->setDescription('测试活动描述');
        $activity->setStartTime(new CarbonImmutable('-1 hour'));
        $activity->setEndTime(new CarbonImmutable('+1 hour'));
        $activity->setValid(true);

        return $activity;
    }

    private function createTestPool(): Pool
    {
        $pool = new Pool();
        $pool->setName('测试奖池');
        $pool->setDescription('测试奖池描述');
        $pool->setValid(true);
        $pool->setSortNumber(1);

        return $pool;
    }

    private function createAwardWithQuantity(string $name, int $quantity, Pool $pool): Award
    {
        $spu = new Spu();
        $spu->setTitle('测试SPU');
        self::getEntityManager()->persist($spu);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setUnit('个');
        $sku->setValid(true);
        self::getEntityManager()->persist($sku);
        // 立即保存以获取 ID
        self::getEntityManager()->flush();

        $award = new Award();
        $award->setName($name);
        $award->setDescription('测试奖品描述');
        $award->setPool($pool);
        $award->setSku($sku);
        $award->setProbability(1000);
        $award->setQuantity($quantity);
        $award->setValue('10.00');
        $award->setAmount(1);
        $award->setNeedConsignee(true);
        $award->setValid(true);
        $award->setSortNumber(1);
        $award->setDayLimit(100); // 设置一个较高的日限额

        return $award;
    }
}