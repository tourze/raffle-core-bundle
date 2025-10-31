<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Repository\ActivityRepository;
use Tourze\RaffleCoreBundle\Service\ActivityService;

/**
 * @internal
 */
#[CoversClass(ActivityService::class)]
final class ActivityServiceTest extends TestCase
{
    private ActivityService $activityService;

    /** @var ActivityRepository&MockObject */
    private ActivityRepository $activityRepository;

    protected function setUp(): void
    {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->activityService = new ActivityService($this->activityRepository);
    }

    public function testGetActiveActivitiesShouldReturnActivitiesFromRepository(): void
    {
        $expectedActivities = [
            $this->createActiveActivity('活动1'),
            $this->createActiveActivity('活动2'),
        ];

        $this->activityRepository
            ->expects($this->once())
            ->method('findActiveActivities')
            ->willReturn($expectedActivities)
        ;

        $result = $this->activityService->getActiveActivities();

        $this->assertSame($expectedActivities, $result);
        $this->assertCount(2, $result);
    }

    public function testGetUpcomingActivitiesShouldReturnLimitedResults(): void
    {
        $expectedActivities = [
            $this->createUpcomingActivity('即将开始1'),
            $this->createUpcomingActivity('即将开始2'),
        ];

        $this->activityRepository
            ->expects($this->once())
            ->method('findUpcomingActivities')
            ->with(5)
            ->willReturn($expectedActivities)
        ;

        $result = $this->activityService->getUpcomingActivities(5);

        $this->assertSame($expectedActivities, $result);
        $this->assertCount(2, $result);
    }

    public function testGetUpcomingActivitiesShouldUseDefaultLimit(): void
    {
        $this->activityRepository
            ->expects($this->once())
            ->method('findUpcomingActivities')
            ->with(10)
            ->willReturn([])
        ;

        $this->activityService->getUpcomingActivities();
    }

    public function testIsActivityActiveShouldReturnTrueForActiveActivity(): void
    {
        $activity = $this->createActiveActivity('测试活动');

        $result = $this->activityService->isActivityActive($activity);

        $this->assertTrue($result);
    }

    public function testIsActivityActiveShouldReturnFalseForInactiveActivity(): void
    {
        $activity = $this->createInactiveActivity('未开始活动');

        $result = $this->activityService->isActivityActive($activity);

        $this->assertFalse($result);
    }

    public function testGetActivityStatusShouldReturnInactiveForInvalidActivity(): void
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('isValid')->willReturn(false);

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('inactive', $result);
    }

    public function testGetActivityStatusShouldReturnUpcomingForFutureActivity(): void
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('isValid')->willReturn(true);
        $activity->method('getStartTime')->willReturn(CarbonImmutable::now()->addHour());
        $activity->method('getEndTime')->willReturn(CarbonImmutable::now()->addHours(2));

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('upcoming', $result);
    }

    public function testGetActivityStatusShouldReturnEndedForExpiredActivity(): void
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('isValid')->willReturn(true);
        $activity->method('getStartTime')->willReturn(CarbonImmutable::now()->subHours(2));
        $activity->method('getEndTime')->willReturn(CarbonImmutable::now()->subHour());

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('ended', $result);
    }

    public function testGetActivityStatusShouldReturnActiveForCurrentActivity(): void
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('isValid')->willReturn(true);
        $activity->method('getStartTime')->willReturn(CarbonImmutable::now()->subHour());
        $activity->method('getEndTime')->willReturn(CarbonImmutable::now()->addHour());

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('active', $result);
    }

    private function createActiveActivity(string $title): Activity
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('isActive')->willReturn(true);
        $activity->method('getTitle')->willReturn($title);

        return $activity;
    }

    private function createUpcomingActivity(string $title): Activity
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('isActive')->willReturn(false);
        $activity->method('getTitle')->willReturn($title);
        $activity->method('getStartTime')->willReturn(CarbonImmutable::now()->addHour());

        return $activity;
    }

    private function createInactiveActivity(string $title): Activity
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $activity->method('isActive')->willReturn(false);
        $activity->method('getTitle')->willReturn($title);

        return $activity;
    }
}
