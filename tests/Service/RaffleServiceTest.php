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
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Entity\Pool;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ActivityInactiveException;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Service\RaffleService;

/**
 * @internal
 */
#[CoversClass(RaffleService::class)]
#[RunTestsInSeparateProcesses]
final class RaffleServiceTest extends AbstractIntegrationTestCase
{
    private RaffleService $raffleService;

    protected function onSetUp(): void
    {
        $this->raffleService = self::getService(RaffleService::class);
    }

    public function testParticipateInLotteryShouldCreateChanceForActiveActivity(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->raffleService->participateInLottery($user, $activity);

        $this->assertInstanceOf(Chance::class, $result);
        $this->assertSame($activity, $result->getActivity());
        $this->assertSame($user, $result->getUser());
        $this->assertSame(ChanceStatusEnum::INIT, $result->getStatus());
        $this->assertNotNull($result->getId());
    }

    public function testParticipateInLotteryShouldThrowExceptionForInactiveActivity(): void
    {
        $user = $this->createNormalUser('test2@example.com', 'password');
        $activity = $this->createInactiveActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $this->expectException(ActivityInactiveException::class);
        $this->expectExceptionMessage('活动未开始或已结束');

        $this->raffleService->participateInLottery($user, $activity);
    }

    public function testDrawPrizeShouldThrowExceptionForUsedChance(): void
    {
        $user = $this->createNormalUser('test3@example.com', 'password');
        $activity = $this->createTestActivity();
        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::WINNING);

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('抽奖机会已被使用');

        $this->raffleService->drawPrize($chance);
    }

    public function testDrawPrizeShouldThrowExceptionForInactiveActivity(): void
    {
        $user = $this->createNormalUser('test4@example.com', 'password');
        $activity = $this->createInactiveActivity();
        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::INIT);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $this->expectException(ActivityInactiveException::class);
        $this->expectExceptionMessage('活动未开始或已结束');

        $this->raffleService->drawPrize($chance);
    }

    public function testDrawPrizeShouldReturnNullWhenNoEligibleAwards(): void
    {
        $user = $this->createNormalUser('test5@example.com', 'password');
        $activity = $this->createTestActivity();
        // 不创建任何奖品，确保没有可用的奖项

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $this->raffleService->drawPrize($chance);

        $this->assertNull($result);

        // 验证chance状态已更新
        self::getEntityManager()->refresh($chance);
        $this->assertSame(ChanceStatusEnum::EXPIRED, $chance->getStatus());
    }

    public function testDrawPrizeShouldReturnAwardWhenWinning(): void
    {
        $user = $this->createNormalUser('test6@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);
        // 设置100%中奖概率
        $award->setProbability(10000);
        $award->setQuantity(10);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $this->raffleService->drawPrize($chance);

        $this->assertSame($award, $result);

        // 验证chance状态已更新
        self::getEntityManager()->refresh($chance);
        $this->assertSame(ChanceStatusEnum::WINNING, $chance->getStatus());
        $this->assertSame($award, $chance->getAward());

        // 验证奖品库存已减少
        self::getEntityManager()->refresh($award);
        $this->assertEquals(9, $award->getQuantity());
    }

    public function testGetUserLotteryHistoryShouldReturnHistoryForSpecificActivity(): void
    {
        $user = $this->createNormalUser('test7@example.com', 'password');
        $activity = $this->createTestActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $this->raffleService->getUserLotteryHistory($user, $activity);

        $this->assertCount(1, $result);
        $this->assertSame($chance, $result[0]);
    }

    public function testGetUserLotteryHistoryShouldReturnWinningHistoryWhenActivityIsNull(): void
    {
        $user = $this->createNormalUser('test8@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::WINNING);
        $chance->setAward($award);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $this->raffleService->getUserLotteryHistory($user, null, 15);

        $this->assertCount(1, $result);
        $this->assertSame($chance, $result[0]);
    }

    public function testCanUserParticipateShouldReturnFalseForInactiveActivity(): void
    {
        $user = $this->createNormalUser('test9@example.com', 'password');
        $activity = $this->createInactiveActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->raffleService->canUserParticipate($user, $activity);

        $this->assertFalse($result);
    }

    public function testCanUserParticipateShouldReturnTrueWhenActivityActiveAndAwardsAvailable(): void
    {
        $user = $this->createNormalUser('test10@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->raffleService->canUserParticipate($user, $activity);

        $this->assertTrue($result);
    }

    private function createTestActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('测试抽奖活动');
        $activity->setDescription('测试用途');
        $activity->setStartTime(new CarbonImmutable('-1 hour'));
        $activity->setEndTime(new CarbonImmutable('+1 hour'));
        $activity->setValid(true);

        return $activity;
    }

    private function createInactiveActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('未开始活动');
        $activity->setDescription('测试用途');
        $activity->setStartTime(new CarbonImmutable('+1 hour'));
        $activity->setEndTime(new CarbonImmutable('+2 hours'));
        $activity->setValid(true);

        return $activity;
    }

    private function createTestPool(): Pool
    {
        $pool = new Pool();
        $pool->setName('测试奖池');
        $pool->setDescription('测试用途');
        $pool->setValid(true);
        $pool->setSortNumber(1);

        return $pool;
    }

    private function createTestAward(Pool $pool): Award
    {
        $spu = new Spu();
        $spu->setTitle('测试SPU商品');
        self::getEntityManager()->persist($spu);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setUnit('个');
        $sku->setValid(true);
        self::getEntityManager()->persist($sku);

        $award = new Award();
        $award->setName('测试奖品');
        $award->setDescription('测试奖品描述');
        $award->setPool($pool);
        $award->setSku($sku);
        $award->setProbability(1000); // 10% probability
        $award->setQuantity(100);
        $award->setValue('10.00');
        $award->setAmount(1);
        $award->setNeedConsignee(true);
        $award->setValid(true);
        $award->setSortNumber(1);

        return $award;
    }
}