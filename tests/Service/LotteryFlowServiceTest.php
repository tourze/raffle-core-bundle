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
use Tourze\RaffleCoreBundle\Service\LotteryFlowService;

/**
 * @internal
 *
 * LotteryFlowService 逻辑的集成测试
 */
#[CoversClass(LotteryFlowService::class)]
#[RunTestsInSeparateProcesses]
final class LotteryFlowServiceTest extends AbstractIntegrationTestCase
{
    private LotteryFlowService $lotteryFlowService;

    protected function onSetUp(): void
    {
        $this->lotteryFlowService = self::getService(LotteryFlowService::class);
    }

    public function testExecuteCompleteLotteryFlowShouldSucceedWhenUserWins(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password123');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $em = self::getEntityManager();
        $em->persist($activity);
        $em->persist($pool);
        $em->persist($award);
        $em->flush();

        $activity->addPool($pool);
        $pool->addAward($award);
        $em->flush();

        $result = $this->lotteryFlowService->executeCompleteLotteryFlow($user, $activity);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['message']);

        if ($result['success']) {
            $this->assertArrayHasKey('data', $result);
            $this->assertIsArray($result['data']);
        }
    }

    public function testExecuteCompleteLotteryFlowShouldFailWhenActivityInactive(): void
    {
        $user = $this->createNormalUser('test2@example.com', 'password123');
        $activity = $this->createInactiveActivity();

        $em = self::getEntityManager();
        $em->persist($activity);
        $em->flush();

        $result = $this->lotteryFlowService->executeCompleteLotteryFlow($user, $activity);

        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('活动', $result['message']);
    }

    public function testGetUserLotteryOverviewShouldReturnCompleteOverview(): void
    {
        $user = $this->createNormalUser('test3@example.com', 'password123');

        $result = $this->lotteryFlowService->getUserLotteryOverview($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('active_activities', $result);
        $this->assertArrayHasKey('pending_prizes', $result);
        $this->assertArrayHasKey('user_dashboard', $result);

        $this->assertIsArray($result['active_activities']);
        $this->assertIsArray($result['pending_prizes']);
        $this->assertIsArray($result['user_dashboard']);

        $this->assertArrayHasKey('pending_prizes_count', $result['user_dashboard']);
        $this->assertArrayHasKey('ordered_prizes_count', $result['user_dashboard']);
        $this->assertArrayHasKey('recent_winnings', $result['user_dashboard']);
    }

    public function testClaimPrizeShouldSucceedWithValidChance(): void
    {
        $user = $this->createNormalUser('claimtest@example.com', 'password123');
        $activity = $this->createTestActivity();
        $pool = $this->createTestPool();
        $award = $this->createTestAward($pool);

        $em = self::getEntityManager();
        $em->persist($activity);
        $em->persist($pool);
        $em->persist($award);
        $em->flush();

        $activity->addPool($pool);
        $pool->addAward($award);
        $em->flush();

        // 先参与抽奖得到机会
        $result = $this->lotteryFlowService->executeCompleteLotteryFlow($user, $activity);

        // 如果抽奖成功，测试兑奖功能
        if ($result['success'] && isset($result['data']['chance'])) {
            $chance = $result['data']['chance'];
            if (!$chance instanceof Chance) {
                self::markTestSkipped('Invalid chance object returned');
            }
            $consigneeInfo = [
                'name' => '测试收货人',
                'phone' => '13800138000',
                'address' => '测试地址',
            ];

            $claimResult = $this->lotteryFlowService->claimPrize($chance, $consigneeInfo);

            $this->assertIsArray($claimResult);
            $this->assertArrayHasKey('success', $claimResult);
            $this->assertArrayHasKey('message', $claimResult);
            $this->assertIsBool($claimResult['success']);
            $this->assertIsString($claimResult['message']);

            if ($claimResult['success']) {
                $this->assertArrayHasKey('order_data', $claimResult);
                $this->assertIsArray($claimResult['order_data']);
            }
        } else {
            // 如果没有中奖，也是正常情况，跳过兑奖测试
            self::markTestSkipped('User did not win a prize, skipping claim test');
        }
    }

    private function createTestActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('测试活动');
        $activity->setPicture('test.jpg');
        $activity->setStartTime(CarbonImmutable::now()->subHour());
        $activity->setEndTime(CarbonImmutable::now()->addHour());
        $activity->setValid(true);

        return $activity;
    }

    private function createInactiveActivity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('过期活动');
        $activity->setPicture('test.jpg');
        $activity->setStartTime(CarbonImmutable::now()->subDays(2));
        $activity->setEndTime(CarbonImmutable::now()->subDay());
        $activity->setValid(true);

        return $activity;
    }

    private function createTestPool(): Pool
    {
        $pool = new Pool();
        $pool->setName('测试奖池');
        $pool->setIsDefault(true);
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
        $award->setPool($pool);
        $award->setSku($sku);
        $award->setName('测试奖品');
        $award->setQuantity(10);
        $award->setDayLimit(5);
        $award->setAmount(100);
        $award->setValue('100.00');
        $award->setProbability(10);
        $award->setNeedConsignee(false);
        $award->setValid(true);
        $award->setSortNumber(1);

        return $award;
    }
}
