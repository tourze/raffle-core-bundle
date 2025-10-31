<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ActivityInactiveException;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Repository\AwardRepository;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;
use Tourze\RaffleCoreBundle\Service\RaffleService;

/**
 * @internal
 */
#[CoversClass(RaffleService::class)]
final class RaffleServiceTest extends TestCase
{
    private RaffleService $raffleService;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var AwardRepository&MockObject */
    private AwardRepository $awardRepository;

    /** @var ChanceRepository&MockObject */
    private ChanceRepository $chanceRepository;

    protected function setUp(): void
    {
        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var AwardRepository&MockObject $awardRepository */
        $awardRepository = $this->createMock(AwardRepository::class);
        $this->awardRepository = $awardRepository;

        /** @var ChanceRepository&MockObject $chanceRepository */
        $chanceRepository = $this->createMock(ChanceRepository::class);
        $this->chanceRepository = $chanceRepository;

        $this->raffleService = new RaffleService(
            $this->entityManager,
            $this->awardRepository,
            $this->chanceRepository
        );
    }

    public function testParticipateInLotteryShouldCreateChanceForActiveActivity(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);

        $activity->method('isActive')->willReturn(true);

        $this->chanceRepository
            ->expects($this->once())
            ->method('save')
            ->with(
                self::callback(function (Chance $chance) use ($activity, $user) {
                    return $chance->getActivity() === $activity
                        && $chance->getUser() === $user
                        && ChanceStatusEnum::INIT === $chance->getStatus();
                }),
                true
            )
        ;

        $result = $this->raffleService->participateInLottery($user, $activity);

        $this->assertInstanceOf(Chance::class, $result);
        $this->assertSame($activity, $result->getActivity());
        $this->assertSame($user, $result->getUser());
        $this->assertSame(ChanceStatusEnum::INIT, $result->getStatus());
    }

    public function testParticipateInLotteryShouldThrowExceptionForInactiveActivity(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);

        $activity->method('isActive')->willReturn(false);

        $this->expectException(ActivityInactiveException::class);
        $this->expectExceptionMessage('活动未开始或已结束');

        $this->raffleService->participateInLottery($user, $activity);
    }

    public function testDrawPrizeShouldThrowExceptionForUsedChance(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn(ChanceStatusEnum::WINNING);

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('抽奖机会已被使用');

        $this->raffleService->drawPrize($chance);
    }

    public function testDrawPrizeShouldThrowExceptionForInactiveActivity(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::INIT);
        $chance->method('getActivity')->willReturn($activity);
        $activity->method('isActive')->willReturn(false);

        $this->expectException(ActivityInactiveException::class);
        $this->expectExceptionMessage('活动未开始或已结束');

        $this->raffleService->drawPrize($chance);
    }

    public function testDrawPrizeShouldReturnNullWhenNoEligibleAwards(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::INIT);
        $chance->method('getActivity')->willReturn($activity);
        $activity->method('isActive')->willReturn(true);

        $this->awardRepository
            ->method('findEligibleForLotteryByActivity')
            ->with($activity)
            ->willReturn([])
        ;

        $chance->expects($this->once())->method('setStatus')->with(ChanceStatusEnum::EXPIRED);
        $this->chanceRepository->expects($this->once())->method('save')->with($chance, true);

        $result = $this->raffleService->drawPrize($chance);

        $this->assertNull($result);
    }

    public function testDrawPrizeShouldReturnAwardWhenWinning(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::INIT);
        $chance->method('getActivity')->willReturn($activity);
        $activity->method('isActive')->willReturn(true);

        $this->awardRepository
            ->method('findEligibleForLotteryByActivity')
            ->willReturn([$award])
        ;

        $this->awardRepository
            ->method('decreaseQuantityAtomically')
            ->with($award, 1)
            ->willReturn(true)
        ;

        $award->method('getName')->willReturn('测试奖品');
        $award->method('getProbability')->willReturn(10000); // 100% probability

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('commit');

        $chance->expects($this->once())->method('markAsWinning')
            ->with($award, self::callback(fn ($arg) => is_array($arg)))
        ;

        $this->chanceRepository->expects($this->once())->method('save')->with($chance, true);

        $result = $this->raffleService->drawPrize($chance);

        $this->assertSame($award, $result);
    }

    public function testDrawPrizeShouldReturnNullWhenStockDecreaseFails(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::INIT);
        $chance->method('getActivity')->willReturn($activity);
        $activity->method('isActive')->willReturn(true);

        $this->awardRepository
            ->method('findEligibleForLotteryByActivity')
            ->willReturn([$award])
        ;

        $this->awardRepository
            ->method('decreaseQuantityAtomically')
            ->willReturn(false)
        ;

        $award->method('getProbability')->willReturn(10000);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');

        $chance->expects($this->once())->method('setStatus')->with(ChanceStatusEnum::EXPIRED);
        $this->chanceRepository->expects($this->once())->method('save')->with($chance, true);

        $result = $this->raffleService->drawPrize($chance);

        $this->assertNull($result);
    }

    public function testDrawPrizeShouldHandleExceptionsAndRollback(): void
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::INIT);
        $chance->method('getActivity')->willReturn($activity);
        $activity->method('isActive')->willReturn(true);

        $this->awardRepository
            ->method('findEligibleForLotteryByActivity')
            ->willReturn([$award])
        ;

        $this->awardRepository
            ->method('decreaseQuantityAtomically')
            ->willThrowException(new \Exception('数据库错误'))
        ;

        $award->method('getProbability')->willReturn(10000);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('数据库错误');

        $this->raffleService->drawPrize($chance);
    }

    public function testGetUserLotteryHistoryShouldReturnHistoryForSpecificActivity(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $expectedChances = [
            $chance,
        ];

        $this->chanceRepository
            ->expects($this->once())
            ->method('findByUserAndActivity')
            ->with($user, $activity)
            ->willReturn($expectedChances)
        ;

        $result = $this->raffleService->getUserLotteryHistory($user, $activity);

        $this->assertSame($expectedChances, $result);
    }

    public function testGetUserLotteryHistoryShouldReturnWinningHistoryWhenActivityIsNull(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $expectedChances = [
            $chance,
        ];

        $this->chanceRepository
            ->expects($this->once())
            ->method('findWinningChancesByUser')
            ->with($user, 15)
            ->willReturn($expectedChances)
        ;

        $result = $this->raffleService->getUserLotteryHistory($user, null, 15);

        $this->assertSame($expectedChances, $result);
    }

    public function testCanUserParticipateShouldReturnFalseForInactiveActivity(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);

        $activity->method('isActive')->willReturn(false);

        $result = $this->raffleService->canUserParticipate($user, $activity);

        $this->assertFalse($result);
    }

    public function testCanUserParticipateShouldReturnFalseWhenNoEligibleAwards(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);

        $activity->method('isActive')->willReturn(true);

        $this->awardRepository
            ->method('findEligibleForLotteryByActivity')
            ->with($activity)
            ->willReturn([])
        ;

        $result = $this->raffleService->canUserParticipate($user, $activity);

        $this->assertFalse($result);
    }

    public function testCanUserParticipateShouldReturnTrueWhenActivityActiveAndAwardsAvailable(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $activity->method('isActive')->willReturn(true);

        $this->awardRepository
            ->method('findEligibleForLotteryByActivity')
            ->with($activity)
            ->willReturn([$award])
        ;

        $result = $this->raffleService->canUserParticipate($user, $activity);

        $this->assertTrue($result);
    }

    public function testCalculateAwardWeightShouldReturnZeroForZeroProbability(): void
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('getProbability')->willReturn(0);

        $reflection = new \ReflectionClass($this->raffleService);
        $method = $reflection->getMethod('calculateAwardWeight');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->raffleService, [$award]);

        $this->assertSame(0, $result);
    }

    public function testCalculateAwardWeightShouldCalculateCorrectWeight(): void
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('getProbability')->willReturn(100); // 1% probability

        $reflection = new \ReflectionClass($this->raffleService);
        $method = $reflection->getMethod('calculateAwardWeight');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->raffleService, [$award]);

        $this->assertSame(100, $result); // 10000/100 = 100
    }

    public function testSelectAwardByProbabilityShouldReturnNullForEmptyAwards(): void
    {
        $reflection = new \ReflectionClass($this->raffleService);
        $method = $reflection->getMethod('selectAwardByProbability');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->raffleService, [[]]);

        $this->assertNull($result);
    }
}
