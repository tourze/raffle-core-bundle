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
use Tourze\RaffleCoreBundle\Exception\InvalidPrizeException;
use Tourze\RaffleCoreBundle\Service\PrizeOrderService;

/**
 * @internal
 */
#[CoversClass(PrizeOrderService::class)]
#[RunTestsInSeparateProcesses]
final class PrizeOrderServiceTest extends AbstractIntegrationTestCase
{
    private PrizeOrderService $prizeOrderService;

    protected function onSetUp(): void
    {
        $this->prizeOrderService = self::getService(PrizeOrderService::class);
    }

    public function testCreateOrderFromPrizeShouldReturnOrderData(): void
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

        $result = $this->prizeOrderService->createOrderFromPrize($chance, $consigneeInfo);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('chance_id', $result);
        $this->assertArrayHasKey('award_name', $result);
        $this->assertArrayHasKey('award_value', $result);
        $this->assertArrayHasKey('consignee_info', $result);
        $this->assertArrayHasKey('order_time', $result);
        $this->assertEquals($chance->getId(), $result['chance_id']);
        $this->assertEquals($award->getName(), $result['award_name']);
    }

    public function testCreateOrderFromPrizeShouldThrowExceptionForNonWinningChance(): void
    {
        $user = $this->createNormalUser('test2@example.com', 'password');
        $activity = $this->createTestActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $consigneeInfo = [
            'name' => '测试收货人',
            'phone' => '13800138000',
            'address' => '测试地址',
        ];

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('该中奖记录已处理或状态不正确');

        $this->prizeOrderService->createOrderFromPrize($chance, $consigneeInfo);
    }

    public function testCreateOrderFromPrizeShouldThrowExceptionForOrderedChance(): void
    {
        $user = $this->createNormalUser('test3@example.com', 'password');
        $activity = $this->createTestActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::ORDERED);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $consigneeInfo = [
            'name' => '测试收货人',
            'phone' => '13800138000',
            'address' => '测试地址',
        ];

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('该中奖记录已处理或状态不正确');

        $this->prizeOrderService->createOrderFromPrize($chance, $consigneeInfo);
    }

    public function testValidatePrizeClaimableShouldReturnTrueForValidPrize(): void
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

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::WINNING);
        $chance->setAward($award);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $this->prizeOrderService->validatePrizeClaimable($chance);

        $this->assertTrue($result);
    }

    public function testValidatePrizeClaimableShouldReturnFalseForNonWinningChance(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password');
        $activity = $this->createTestActivity();

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::INIT);
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $this->prizeOrderService->validatePrizeClaimable($chance);

        $this->assertFalse($result);
    }

    public function testGetPrizeOrderInfoShouldReturnOrderInfo(): void
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

        $chance = new Chance();
        $chance->setUser($user);
        $chance->setActivity($activity);
        $chance->setStatus(ChanceStatusEnum::WINNING);
        $chance->setAward($award);
        $chance->setUseTime(new CarbonImmutable());
        self::getEntityManager()->persist($chance);
        self::getEntityManager()->flush();

        $result = $this->prizeOrderService->getPrizeOrderInfo($chance);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('chance_id', $result);
        $this->assertArrayHasKey('award_name', $result);
        $this->assertArrayHasKey('award_value', $result);
        $this->assertArrayHasKey('need_consignee', $result);
        $this->assertArrayHasKey('win_time', $result);
        $this->assertEquals($chance->getId(), $result['chance_id']);
        $this->assertEquals($award->getName(), $result['award_name']);
        $this->assertEquals($award->getValue(), $result['award_value']);
        $this->assertEquals($award->isNeedConsignee(), $result['need_consignee']);
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
}