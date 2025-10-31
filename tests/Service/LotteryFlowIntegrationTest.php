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
use Tourze\RaffleCoreBundle\Service\LotteryFlowService;

/**
 * @internal
 */
#[CoversClass(LotteryFlowService::class)]
#[RunTestsInSeparateProcesses]
final class LotteryFlowIntegrationTest extends AbstractIntegrationTestCase
{
    private LotteryFlowService $lotteryFlowService;

    protected function onSetUp(): void
    {
        $this->lotteryFlowService = self::getService(LotteryFlowService::class);
    }

    public function testCompleteSuccessfulLotteryFlow(): void
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

        $result = $this->lotteryFlowService->executeCompleteLotteryFlow($user, $activity);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']);
        $this->assertIsString($result['message']);

        if (is_array($result['data']) && is_bool($result['data']['won'] ?? false) && ($result['data']['won'] ?? false)) {
            $this->assertInstanceOf(Award::class, $result['data']['award']);
            $this->assertArrayHasKey('prize_info', $result['data']);
            $this->assertIsBool($result['data']['need_consignee']);
        }
    }

    public function testLotteryFlowWithInactiveActivity(): void
    {
        $user = $this->createNormalUser('test2@example.com', 'password');
        $activity = $this->createInactiveActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->lotteryFlowService->executeCompleteLotteryFlow($user, $activity);

        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('活动', $result['message']);
    }

    public function testUserLotteryOverview(): void
    {
        $user = $this->createNormalUser('test3@example.com', 'password');

        $overview = $this->lotteryFlowService->getUserLotteryOverview($user);

        $this->assertIsArray($overview['active_activities']);
        $this->assertIsArray($overview['user_dashboard']);
        $this->assertIsArray($overview['pending_prizes']);

        $this->assertArrayHasKey('pending_prizes_count', $overview['user_dashboard']);
        $this->assertArrayHasKey('ordered_prizes_count', $overview['user_dashboard']);
        $this->assertArrayHasKey('recent_winnings', $overview['user_dashboard']);
    }

    public function testExecuteCompleteLotteryFlow(): void
    {
        $user = $this->createNormalUser('complete@example.com', 'password');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $activity->addPool($pool);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->persist($pool);
        self::getEntityManager()->persist($award);
        self::getEntityManager()->flush();

        $result = $this->lotteryFlowService->executeCompleteLotteryFlow($user, $activity);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testClaimPrize(): void
    {
        $user = $this->createNormalUser('claim@example.com', 'password');
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

        $consigneeInfo = [
            'name' => '测试收货人',
            'phone' => '13800138000',
            'address' => '测试地址',
        ];

        $result = $this->lotteryFlowService->claimPrize($chance, $consigneeInfo);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    private function createTestActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('测试抽奖活动');
        $activity->setDescription('测试用途');
        $activity->setStartTime(CarbonImmutable::now()->subHour());
        $activity->setEndTime(CarbonImmutable::now()->addHour());
        $activity->setValid(true);

        return $activity;
    }

    private function createInactiveActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('未开始活动');
        $activity->setDescription('测试用途');
        $activity->setStartTime(CarbonImmutable::now()->addHour());
        $activity->setEndTime(CarbonImmutable::now()->addHours(2));
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
        $award->setProbability(10000);
        $award->setQuantity(100);
        $award->setValue('10.00');
        $award->setAmount(1);
        $award->setNeedConsignee(true);
        $award->setValid(true);
        $award->setSortNumber(1);

        return $award;
    }
}
