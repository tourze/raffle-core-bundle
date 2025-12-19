<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Entity\Pool;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Service\ChanceService;

/**
 * @internal
 */
#[CoversClass(ChanceService::class)]
#[RunTestsInSeparateProcesses]
final class ChanceServiceTest extends AbstractIntegrationTestCase
{
    private ChanceService $chanceService;

    protected function onSetUp(): void
    {
        $this->chanceService = self::getService(ChanceService::class);
    }

    public function testGetUserChancesByActivityShouldReturnChancesFromRepository(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password');
        $activity = $this->createTestActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance1 = $this->createChance($user, $activity, ChanceStatusEnum::INIT);
        $chance2 = $this->createChance($user, $activity, ChanceStatusEnum::WINNING);

        self::getEntityManager()->persist($chance1);
        self::getEntityManager()->persist($chance2);
        self::getEntityManager()->flush();

        $result = $this->chanceService->getUserChancesByActivity($user, $activity);

        $this->assertCount(2, $result);
        $statuses = array_map(fn ($chance) => $chance->getStatus(), $result);
        $this->assertContains(ChanceStatusEnum::INIT, $statuses);
        $this->assertContains(ChanceStatusEnum::WINNING, $statuses);
    }

    public function testMarkChanceAsWinningShouldUpdateChanceStatusAndSetAward(): void
    {
        $user = $this->createNormalUser('test2@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $chance = $this->createChance($user, $activity, ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $prizeInfo = ['level' => 1, 'description' => '一等奖'];

        $this->chanceService->markChanceAsWinning($chance, $award, $prizeInfo);

        self::getEntityManager()->refresh($chance);

        $this->assertSame(ChanceStatusEnum::WINNING, $chance->getStatus());
        $this->assertSame($award, $chance->getAward());
        $this->assertSame($prizeInfo, $chance->getWinContext());
    }

    public function testMarkAsExpiredShouldUpdateChanceStatus(): void
    {
        $user = $this->createNormalUser('test3@example.com', 'password');
        $activity = $this->createTestActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance = $this->createChance($user, $activity, ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $chance->markAsExpired();
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        self::getEntityManager()->refresh($chance);
        $this->assertSame(ChanceStatusEnum::EXPIRED, $chance->getStatus());
    }

    public function testMarkChanceAsOrderedShouldUpdateChanceStatus(): void
    {
        $user = $this->createNormalUser('test4@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $chance = $this->createChance($user, $activity, ChanceStatusEnum::WINNING);
        $chance->setAward($award);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $this->chanceService->markChanceAsOrdered($chance);

        self::getEntityManager()->refresh($chance);
        $this->assertSame(ChanceStatusEnum::ORDERED, $chance->getStatus());
    }

    public function testCanOrderShouldReturnTrueForWinningChance(): void
    {
        $user = $this->createNormalUser('test6@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $chance = $this->createChance($user, $activity, ChanceStatusEnum::WINNING);
        $chance->setAward($award);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $chance->canOrder();

        $this->assertTrue($result);
    }

    public function testCanOrderShouldReturnFalseForInitialChance(): void
    {
        $user = $this->createNormalUser('test7@example.com', 'password');
        $activity = $this->createTestActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance = $this->createChance($user, $activity, ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $chance->canOrder();

        $this->assertFalse($result);
    }

    public function testGetUserWinningHistoryShouldReturnWinningChances(): void
    {
        $user = $this->createNormalUser('test8@example.com', 'password');
        $activity1 = $this->createTestActivity();
        $activity2 = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity1->addPool($pool);
        $activity2->addPool($pool);

        self::getEntityManager()->persist($activity1);
        self::getEntityManager()->persist($activity2);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $winningChance1 = $this->createChance($user, $activity1, ChanceStatusEnum::WINNING);
        $winningChance1->setAward($award);
        $winningChance2 = $this->createChance($user, $activity2, ChanceStatusEnum::WINNING);
        $winningChance2->setAward($award);
        $orderedChance = $this->createChance($user, $activity1, ChanceStatusEnum::ORDERED);

        self::getEntityManager()->persist($winningChance1);
        self::getEntityManager()->persist($winningChance2);
        self::getEntityManager()->persist($orderedChance);
        self::getEntityManager()->flush();

        $result = $this->chanceService->getUserWinningHistory($user, 15);

        $this->assertCount(2, $result);
        $statuses = array_map(fn ($chance) => $chance->getStatus(), $result);
        $this->assertContains(ChanceStatusEnum::WINNING, $statuses);
        // findWinningChancesByUser 只返回 WINNING 状态的记录，不包括 ORDERED 状态
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

    private function createTestAward(Pool $pool): Award
    {
        $sku = $this->createTestSku();

        $award = new Award();
        $award->setName('测试奖品');
        $award->setDescription('测试奖品描述');
        $award->setPool($pool);
        $award->setSku($sku);
        $award->setProbability(1000);
        $award->setQuantity(100);
        $award->setValue('10.00');
        $award->setAmount(1);
        $award->setNeedConsignee(true);
        $award->setValid(true);
        $award->setSortNumber(1);

        return $award;
    }

    private function createTestSku(): \Tourze\ProductCoreBundle\Entity\Sku
    {
        $spu = new \Tourze\ProductCoreBundle\Entity\Spu();
        $spu->setTitle('测试商品SPU ' . uniqid());
        $spu->setContent('测试商品描述');
        $spu->setValid(true);

        $sku = new \Tourze\ProductCoreBundle\Entity\Sku();
        $sku->setSpu($spu);
        $sku->setGtin('TEST_SKU_' . uniqid());
        $sku->setUnit('个');
        $sku->setNeedConsignee(true);
        $sku->setThumbs([['url' => '/images/test-thumb.png']]);

        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        return $sku;
    }

    private function createChance($user, Activity $activity, ChanceStatusEnum $status): Chance
    {
        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus($status);

        return $chance;
    }
}