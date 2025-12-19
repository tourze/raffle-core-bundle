<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Service\ActivityService;

/**
 * @internal
 */
#[CoversClass(ActivityService::class)]
#[RunTestsInSeparateProcesses]
final class ActivityServiceTest extends AbstractIntegrationTestCase
{
    private ActivityService $activityService;

    protected function onSetUp(): void
    {
        $this->activityService = self::getService(ActivityService::class);
    }

    public function testGetActiveActivitiesShouldReturnActivitiesFromRepository(): void
    {
        // 创建一些测试活动
        $activeActivity1 = $this->createActiveActivity('活动1');
        $activeActivity2 = $this->createActiveActivity('活动2');
        $inactiveActivity = $this->createInactiveActivity('未开始活动');

        self::getEntityManager()->persist($activeActivity1);
        self::getEntityManager()->persist($activeActivity2);
        self::getEntityManager()->persist($inactiveActivity);
        self::getEntityManager()->flush();

        $result = $this->activityService->getActiveActivities();

        // 验证只返回活跃的活动
        $this->assertGreaterThanOrEqual(2, $result);
        $titles = array_map(fn ($activity) => $activity->getTitle(), $result);
        $this->assertContains('活动1', $titles);
        $this->assertContains('活动2', $titles);
        $this->assertNotContains('未开始活动', $titles);
    }

    public function testGetUpcomingActivitiesShouldReturnLimitedResults(): void
    {
        // 创建一些即将开始的活动
        $upcomingActivity1 = $this->createUpcomingActivity('即将开始1');
        $upcomingActivity2 = $this->createUpcomingActivity('即将开始2');

        self::getEntityManager()->persist($upcomingActivity1);
        self::getEntityManager()->persist($upcomingActivity2);
        self::getEntityManager()->flush();

        $result = $this->activityService->getUpcomingActivities(5);

        $this->assertGreaterThanOrEqual(2, $result);
        $titles = array_map(fn ($activity) => $activity->getTitle(), $result);
        $this->assertContains('即将开始1', $titles);
        $this->assertContains('即将开始2', $titles);
    }

    public function testGetUpcomingActivitiesShouldUseDefaultLimit(): void
    {
        $upcomingActivity = $this->createUpcomingActivity('即将开始');
        self::getEntityManager()->persist($upcomingActivity);
        self::getEntityManager()->flush();

        $result = $this->activityService->getUpcomingActivities();

        $this->assertIsArray($result);
    }

    public function testIsActivityActiveShouldReturnTrueForActiveActivity(): void
    {
        $activity = $this->createActiveActivity('测试活动');

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->activityService->isActivityActive($activity);

        $this->assertTrue($result);
    }

    public function testIsActivityActiveShouldReturnFalseForInactiveActivity(): void
    {
        $activity = $this->createInactiveActivity('未开始活动');

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->activityService->isActivityActive($activity);

        $this->assertFalse($result);
    }

    public function testGetActivityStatusShouldReturnInactiveForInvalidActivity(): void
    {
        $activity = $this->createInactiveActivity('无效活动');
        $activity->setValid(false);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('inactive', $result);
    }

    public function testGetActivityStatusShouldReturnUpcomingForFutureActivity(): void
    {
        $activity = $this->createUpcomingActivity('即将开始');

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('upcoming', $result);
    }

    public function testGetActivityStatusShouldReturnEndedForExpiredActivity(): void
    {
        $activity = new Activity();
        $activity->setTitle('已结束活动');
        $activity->setStartTime(new CarbonImmutable('-2 hours'));
        $activity->setEndTime(new CarbonImmutable('-1 hour'));
        $activity->setValid(true);

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('ended', $result);
    }

    public function testGetActivityStatusShouldReturnActiveForCurrentActivity(): void
    {
        $activity = $this->createActiveActivity('进行中活动');

        self::getEntityManager()->persist($activity);
        self::getEntityManager()->flush();

        $result = $this->activityService->getActivityStatus($activity);

        $this->assertSame('active', $result);
    }

    private function createActiveActivity(string $title): Activity
    {
        $activity = new Activity();
        $activity->setTitle($title);
        $activity->setDescription('测试活动');
        $activity->setStartTime(new CarbonImmutable('-1 hour'));
        $activity->setEndTime(new CarbonImmutable('+1 hour'));
        $activity->setValid(true);

        return $activity;
    }

    private function createUpcomingActivity(string $title): Activity
    {
        $activity = new Activity();
        $activity->setTitle($title);
        $activity->setDescription('测试活动');
        $activity->setStartTime(new CarbonImmutable('+1 hour'));
        $activity->setEndTime(new CarbonImmutable('+2 hours'));
        $activity->setValid(true);

        return $activity;
    }

    private function createInactiveActivity(string $title): Activity
    {
        $activity = new Activity();
        $activity->setTitle($title);
        $activity->setDescription('测试活动');
        $activity->setStartTime(new CarbonImmutable('-2 hours'));
        $activity->setEndTime(new CarbonImmutable('-1 hour'));
        $activity->setValid(true);

        return $activity;
    }
}