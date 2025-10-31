<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Exception\InvalidPrizeException;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;
use Tourze\RaffleCoreBundle\Service\ChanceService;
use Tourze\RaffleCoreBundle\Service\PrizeOrderService;

/**
 * @internal
 */
#[CoversClass(PrizeOrderService::class)]
final class PrizeOrderServiceTest extends TestCase
{
    private PrizeOrderService $prizeOrderService;

    /** @var ChanceRepository&MockObject */
    private ChanceRepository $chanceRepository;

    /** @var ChanceService&MockObject */
    private ChanceService $chanceService;

    protected function setUp(): void
    {
        $this->chanceRepository = $this->createMock(ChanceRepository::class);
        $this->chanceService = $this->createMock(ChanceService::class);
        $this->prizeOrderService = new PrizeOrderService(
            $this->chanceRepository,
            $this->chanceService
        );
    }

    public function testGetUserPendingPrizesShouldReturnPendingChances(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $expectedChances = [
            $this->createMock(Chance::class),
            $this->createMock(Chance::class),
        ];

        $this->chanceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'user' => $user,
                'status' => ChanceStatusEnum::WINNING,
            ])
            ->willReturn($expectedChances)
        ;

        $result = $this->prizeOrderService->getUserPendingPrizes($user);

        $this->assertSame($expectedChances, $result);
        $this->assertCount(2, $result);
    }

    public function testGetPrizeOrderInfoShouldReturnOrderInfoForValidChance(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $createTime = CarbonImmutable::parse('2023-01-01 10:00:00');
        $useTime = CarbonImmutable::parse('2023-01-01 11:00:00');

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn($award);
        $chance->method('getId')->willReturn(123);
        $chance->method('getUseTime')->willReturn($useTime);
        $chance->method('getCreateTime')->willReturn($createTime);

        $award->method('getName')->willReturn('测试奖品');
        $award->method('getValue')->willReturn('100.00');
        $award->method('isNeedConsignee')->willReturn(true);

        $result = $this->prizeOrderService->getPrizeOrderInfo($chance);

        $this->assertSame(123, $result['chance_id']);
        $this->assertSame('测试奖品', $result['award_name']);
        $this->assertSame('100.00', $result['award_value']);
        $this->assertTrue($result['need_consignee']);
        $this->assertSame($useTime, $result['win_time']);
    }

    public function testGetPrizeOrderInfoShouldUseCreateTimeWhenUseTimeIsNull(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $createTime = CarbonImmutable::parse('2023-01-01 10:00:00');

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn($award);
        $chance->method('getId')->willReturn(123);
        $chance->method('getUseTime')->willReturn(null);
        $chance->method('getCreateTime')->willReturn($createTime);

        $award->method('getName')->willReturn('测试奖品');
        $award->method('getValue')->willReturn('100.00');
        $award->method('isNeedConsignee')->willReturn(false);

        $result = $this->prizeOrderService->getPrizeOrderInfo($chance);

        $this->assertSame($createTime, $result['win_time']);
        $this->assertFalse($result['need_consignee']);
    }

    public function testGetPrizeOrderInfoShouldThrowExceptionForWrongStatus(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn(ChanceStatusEnum::INIT);

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('该中奖记录状态不正确');

        $this->prizeOrderService->getPrizeOrderInfo($chance);
    }

    public function testGetPrizeOrderInfoShouldThrowExceptionWhenAwardIsNull(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn(null);

        $this->expectException(InvalidPrizeException::class);
        $this->expectExceptionMessage('中奖记录没有关联奖品');

        $this->prizeOrderService->getPrizeOrderInfo($chance);
    }

    public function testGetPrizeOrderInfoShouldThrowExceptionWhenChanceIdIsNull(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn($award);
        $chance->method('getId')->willReturn(null);

        $this->expectException(InvalidPrizeException::class);
        $this->expectExceptionMessage('中奖记录ID无效');

        $this->prizeOrderService->getPrizeOrderInfo($chance);
    }

    public function testCreateOrderFromPrizeShouldSucceedForValidChance(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $consigneeInfo = ['name' => '收货人', 'phone' => '13800138000'];

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn($award);
        $chance->method('getId')->willReturn(456);

        $award->method('getName')->willReturn('测试奖品');
        $award->method('getValue')->willReturn('50.00');

        $this->chanceService
            ->expects($this->once())
            ->method('markChanceAsOrdered')
            ->with($chance)
            ->willReturn($chance)
        ;

        $result = $this->prizeOrderService->createOrderFromPrize($chance, $consigneeInfo);

        $this->assertSame(456, $result['chance_id']);
        $this->assertSame('测试奖品', $result['award_name']);
        $this->assertSame('50.00', $result['award_value']);
        $this->assertSame($consigneeInfo, $result['consignee_info']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['order_time']);
    }

    public function testCreateOrderFromPrizeShouldThrowExceptionForWrongStatus(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn(ChanceStatusEnum::ORDERED);

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('该中奖记录已处理或状态不正确');

        $this->prizeOrderService->createOrderFromPrize($chance);
    }

    public function testCreateOrderFromPrizeShouldThrowExceptionWhenAwardIsNull(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn(null);

        $this->expectException(InvalidPrizeException::class);
        $this->expectExceptionMessage('中奖记录没有关联奖品');

        $this->prizeOrderService->createOrderFromPrize($chance);
    }

    public function testValidatePrizeClaimableShouldReturnTrueForValidPrize(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn($award);
        $award->method('isValid')->willReturn(true);

        $result = $this->prizeOrderService->validatePrizeClaimable($chance);

        $this->assertTrue($result);
    }

    public function testValidatePrizeClaimableShouldReturnFalseForWrongStatus(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn(ChanceStatusEnum::ORDERED);

        $result = $this->prizeOrderService->validatePrizeClaimable($chance);

        $this->assertFalse($result);
    }

    public function testValidatePrizeClaimableShouldReturnFalseWhenAwardIsNull(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn(null);

        $result = $this->prizeOrderService->validatePrizeClaimable($chance);

        $this->assertFalse($result);
    }

    public function testValidatePrizeClaimableShouldReturnFalseForInvalidAward(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);
        $chance->method('getAward')->willReturn($award);
        $award->method('isValid')->willReturn(false);

        $result = $this->prizeOrderService->validatePrizeClaimable($chance);

        $this->assertFalse($result);
    }

    public function testGetUserOrderedPrizesShouldReturnOrderedChances(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $expectedChances = [
            $this->createMock(Chance::class),
        ];

        $this->chanceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                ['user' => $user, 'status' => ChanceStatusEnum::ORDERED],
                ['useTime' => 'DESC'],
                10
            )
            ->willReturn($expectedChances)
        ;

        $result = $this->prizeOrderService->getUserOrderedPrizes($user, 10);

        $this->assertSame($expectedChances, $result);
        $this->assertCount(1, $result);
    }

    public function testGetUserOrderedPrizesShouldUseDefaultLimit(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);

        $this->chanceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                ['user' => $user, 'status' => ChanceStatusEnum::ORDERED],
                ['useTime' => 'DESC'],
                20
            )
            ->willReturn([])
        ;

        $this->prizeOrderService->getUserOrderedPrizes($user);
    }
}
