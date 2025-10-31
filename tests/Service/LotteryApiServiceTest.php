<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Service\ActivityService;
use Tourze\RaffleCoreBundle\Service\AwardService;
use Tourze\RaffleCoreBundle\Service\ChanceService;
use Tourze\RaffleCoreBundle\Service\LotteryApiService;
use Tourze\RaffleCoreBundle\Service\RaffleService;

/**
 * @internal
 */
#[CoversClass(LotteryApiService::class)]
final class LotteryApiServiceTest extends TestCase
{
    private LotteryApiService $lotteryApiService;

    /** @var ActivityService&MockObject */
    private ActivityService $activityService;

    /** @var RaffleService&MockObject */
    private RaffleService $raffleService;

    /** @var ChanceService&MockObject */
    private ChanceService $chanceService;

    /** @var AwardService&MockObject */
    private AwardService $awardService;

    protected function setUp(): void
    {
        /** @var ActivityService&MockObject $activityService */
        $activityService = $this->createMock(ActivityService::class);
        $this->activityService = $activityService;

        /** @var RaffleService&MockObject $raffleService */
        $raffleService = $this->createMock(RaffleService::class);
        $this->raffleService = $raffleService;

        /** @var ChanceService&MockObject $chanceService */
        $chanceService = $this->createMock(ChanceService::class);
        $this->chanceService = $chanceService;

        /** @var AwardService&MockObject $awardService */
        $awardService = $this->createMock(AwardService::class);
        $this->awardService = $awardService;

        $this->lotteryApiService = new LotteryApiService(
            $this->activityService,
            $this->raffleService,
            $this->chanceService,
            $this->awardService
        );
    }

    public function testGetAvailableActivitiesForUserShouldFilterByUserParticipation(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $activity1 = $this->createActivity('可参与活动');
        $activity2 = $this->createActivity('不可参与活动');
        $allActivities = [$activity1, $activity2];

        $this->activityService
            ->expects($this->once())
            ->method('getActiveActivities')
            ->willReturn($allActivities)
        ;

        $this->raffleService
            ->expects($this->exactly(2))
            ->method('canUserParticipate')
            ->willReturnMap([
                [$user, $activity1, true],
                [$user, $activity2, false],
            ])
        ;

        $result = $this->lotteryApiService->getAvailableActivitiesForUser($user);

        $this->assertCount(1, $result);
        $this->assertSame($activity1, $result[0]);
    }

    public function testGetActivityDetailsForUserShouldReturnCompleteDetails(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $activity = $this->createActivity('测试活动');
        $awards = [$this->createAward('奖品1'), $this->createAward('奖品2')];

        $this->activityService
            ->method('getActivityStatus')
            ->with($activity)
            ->willReturn('active')
        ;

        $this->raffleService
            ->method('canUserParticipate')
            ->with($user, $activity)
            ->willReturn(true)
        ;

        $this->chanceService
            ->method('getUserChanceCount')
            ->with($user, $activity)
            ->willReturn(3)
        ;

        $this->awardService
            ->method('getAvailableAwardsByActivity')
            ->with($activity)
            ->willReturn($awards)
        ;

        $result = $this->lotteryApiService->getActivityDetailsForUser($activity, $user);

        $this->assertIsArray($result);
        $this->assertSame($activity, $result['activity']);
        $this->assertSame('active', $result['status']);
        $this->assertTrue($result['can_participate']);
        $this->assertSame(3, $result['user_chances_count']);
        $this->assertSame($awards, $result['available_awards']);
    }

    public function testParticipateAndDrawShouldReturnSuccessWithPrizeWhenWinning(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $activity = $this->createActivity('测试活动');
        $chance = $this->createChance();
        $award = $this->createAward('测试奖品');

        $this->raffleService
            ->method('participateInLottery')
            ->with($user, $activity)
            ->willReturn($chance)
        ;

        $this->raffleService
            ->method('drawPrize')
            ->with($chance)
            ->willReturn($award)
        ;

        $result = $this->lotteryApiService->participateAndDraw($user, $activity);

        $this->assertTrue($result['success']);
        $this->assertSame($chance, $result['chance']);
        $this->assertSame($award, $result['award']);
        $this->assertSame('恭喜您中奖了！', $result['message']);
    }

    public function testParticipateAndDrawShouldReturnSuccessWithoutPrizeWhenLosing(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $activity = $this->createActivity('测试活动');
        $chance = $this->createChance();

        $this->raffleService
            ->method('participateInLottery')
            ->with($user, $activity)
            ->willReturn($chance)
        ;

        $this->raffleService
            ->method('drawPrize')
            ->with($chance)
            ->willReturn(null)
        ;

        $result = $this->lotteryApiService->participateAndDraw($user, $activity);

        $this->assertTrue($result['success']);
        $this->assertSame($chance, $result['chance']);
        $this->assertNull($result['award']);
        $this->assertSame('很遗憾，本次未中奖', $result['message']);
    }

    public function testParticipateAndDrawShouldReturnFailureOnException(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $activity = $this->createActivity('测试活动');

        $this->raffleService
            ->method('participateInLottery')
            ->with($user, $activity)
            ->willThrowException(new \Exception('参与抽奖失败'))
        ;

        $result = $this->lotteryApiService->participateAndDraw($user, $activity);

        $this->assertFalse($result['success']);
        $this->assertNull($result['chance']);
        $this->assertNull($result['award']);
        $this->assertSame('参与抽奖失败', $result['message']);
    }

    public function testGetUserLotteryDashboardShouldReturnCompleteStats(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $allChances = [
            $this->createChanceWithStatus(ChanceStatusEnum::INIT),
            $this->createChanceWithStatus(ChanceStatusEnum::WINNING),
            $this->createChanceWithStatus(ChanceStatusEnum::ORDERED),
            $this->createChanceWithStatus(ChanceStatusEnum::WINNING),
        ];

        $this->chanceService
            ->method('getUserWinningHistory')
            ->with($user, 100)
            ->willReturn($allChances)
        ;

        $result = $this->lotteryApiService->getUserLotteryDashboard($user);

        $this->assertIsArray($result);
        $this->assertSame(4, $result['total_participations']);
        $this->assertSame(2, $result['winning_count']);
        $this->assertSame(2, $result['pending_orders']);
        $this->assertCount(4, $result['recent_chances']);
    }

    public function testGetUserLotteryDashboardShouldLimitRecentChances(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $manyChances = array_fill(0, 15, $this->createChanceWithStatus(ChanceStatusEnum::INIT));

        $this->chanceService
            ->method('getUserWinningHistory')
            ->willReturn($manyChances)
        ;

        $result = $this->lotteryApiService->getUserLotteryDashboard($user);

        $this->assertCount(10, $result['recent_chances']);
    }

    private function createActivity(string $title): Activity
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('getTitle')->willReturn($title);

        return $activity;
    }

    private function createAward(string $name): Award
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('getName')->willReturn($name);

        return $award;
    }

    private function createChance(): Chance
    {
        return $this->createMock(Chance::class);
    }

    private function createChanceWithStatus(ChanceStatusEnum $status): Chance
    {
        /** @var Chance&MockObject $chance */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn($status);

        return $chance;
    }
}
