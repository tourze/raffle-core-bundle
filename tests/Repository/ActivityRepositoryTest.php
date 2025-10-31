<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Repository;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Repository\ActivityRepository;

/**
 * @internal
 */
#[CoversClass(ActivityRepository::class)]
#[RunTestsInSeparateProcesses]
final class ActivityRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepository(): ActivityRepository
    {
        return self::getService(ActivityRepository::class);
    }

    protected function createNewEntity(): Activity
    {
        $activity = new Activity();
        $activity->setTitle('Test Activity ' . uniqid());
        $activity->setDescription('Test Description');
        $activity->setStartTime(CarbonImmutable::now());
        $activity->setEndTime(CarbonImmutable::now()->addDay());
        $activity->setValid(true);

        return $activity;
    }

    protected function onSetUp(): void
    {
        // 手动创建测试数据
        $this->loadTestData();
    }

    private function loadTestData(): void
    {
        $now = CarbonImmutable::now();

        $activeActivity = new Activity();
        $activeActivity->setTitle('春节大抽奖活动');
        $activeActivity->setDescription('欢庆春节，参与抽奖赢大奖！丰富奖品等你来拿，每日限量抽奖机会。');
        $activeActivity->setPicture('https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800&h=600&fit=crop');
        $activeActivity->setStartTime($now->subDays(2));
        $activeActivity->setEndTime($now->addDays(5));
        $activeActivity->setValid(true);

        $upcomingActivity = new Activity();
        $upcomingActivity->setTitle('元宵节特别活动');
        $upcomingActivity->setDescription('元宵佳节，团团圆圆！特别准备了精美礼品，快来参与吧！');
        $upcomingActivity->setPicture('https://images.unsplash.com/photo-1612198228254-f204d220d325?w=800&h=600&fit=crop');
        $upcomingActivity->setStartTime($now->addDays(10));
        $upcomingActivity->setEndTime($now->addDays(17));
        $upcomingActivity->setValid(true);

        $expiredActivity = new Activity();
        $expiredActivity->setTitle('新年礼品大放送');
        $expiredActivity->setDescription('新年到，礼品到！感谢大家的参与，活动已结束。');
        $expiredActivity->setPicture('https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&h=600&fit=crop');
        $expiredActivity->setStartTime($now->subDays(30));
        $expiredActivity->setEndTime($now->subDays(15));
        $expiredActivity->setValid(false);

        $em = self::getEntityManager();
        $em->persist($activeActivity);
        $em->persist($upcomingActivity);
        $em->persist($expiredActivity);
        $em->flush();
    }

    public function testSaveActivityWithoutFlushShouldPersistEntity(): void
    {
        $activity = $this->createValidActivity();
        $repository = $this->getRepository();

        $repository->save($activity, false);

        self::getEntityManager()->flush();
        $this->assertNotNull($activity->getId());
    }

    public function testSaveActivityWithFlushShouldPersistImmediately(): void
    {
        $activity = $this->createValidActivity();
        $repository = $this->getRepository();

        $repository->save($activity, true);

        $this->assertNotNull($activity->getId());
    }

    public function testRemoveActivityWithoutFlushShouldMarkForDeletion(): void
    {
        $activity = $this->createValidActivity();
        $repository = $this->getRepository();
        $repository->save($activity, true);
        $activityId = $activity->getId();

        $repository->remove($activity, false);
        self::getEntityManager()->flush();

        $removedActivity = $repository->find($activityId);
        $this->assertNull($removedActivity);
    }

    public function testRemoveActivityWithFlushShouldDeleteImmediately(): void
    {
        $activity = $this->createValidActivity();
        $repository = $this->getRepository();
        $repository->save($activity, true);
        $activityId = $activity->getId();

        $repository->remove($activity, true);

        $removedActivity = $repository->find($activityId);
        $this->assertNull($removedActivity);
    }

    public function testFindActiveActivitiesShouldReturnOnlyValidAndCurrentActivities(): void
    {
        $now = CarbonImmutable::now();

        $activeActivity = $this->createValidActivity();
        $activeActivity->setStartTime($now->subDay());
        $activeActivity->setEndTime($now->addDay());
        $activeActivity->setValid(true);

        $inactiveActivity = $this->createValidActivity();
        $inactiveActivity->setStartTime($now->subDay());
        $inactiveActivity->setEndTime($now->addDay());
        $inactiveActivity->setValid(false);

        $futureActivity = $this->createValidActivity();
        $futureActivity->setStartTime($now->addDay());
        $futureActivity->setEndTime($now->addDays(2));
        $futureActivity->setValid(true);

        $repository = $this->getRepository();
        $repository->save($activeActivity, true);
        $repository->save($inactiveActivity, true);
        $repository->save($futureActivity, true);

        $activeActivities = $repository->findActiveActivities();

        // 验证我们创建的活动在结果中
        $activeIds = array_map(fn ($activity) => $activity->getId(), $activeActivities);
        $this->assertContains($activeActivity->getId(), $activeIds, 'Created active activity should be in results');

        // 验证无效活动不在结果中
        $this->assertNotContains($inactiveActivity->getId(), $activeIds, 'Invalid activity should not be in results');

        // 验证未来活动不在结果中
        $this->assertNotContains($futureActivity->getId(), $activeIds, 'Future activity should not be in results');

        // 验证所有返回的活动都是有效且当前时间内的
        foreach ($activeActivities as $activity) {
            $this->assertTrue($activity->isActive(), 'All returned activities should be active');
        }
    }

    public function testFindUpcomingActivitiesShouldReturnFutureValidActivities(): void
    {
        $now = CarbonImmutable::now();

        $currentActivity = $this->createValidActivity();
        $currentActivity->setStartTime($now->subDay());
        $currentActivity->setEndTime($now->addDay());
        $currentActivity->setValid(true);

        $futureActivity1 = $this->createValidActivity();
        $futureActivity1->setStartTime($now->addHour());
        $futureActivity1->setEndTime($now->addDay());
        $futureActivity1->setValid(true);

        $futureActivity2 = $this->createValidActivity();
        $futureActivity2->setStartTime($now->addDays(2));
        $futureActivity2->setEndTime($now->addDays(3));
        $futureActivity2->setValid(true);

        $repository = $this->getRepository();
        $repository->save($currentActivity, true);
        $repository->save($futureActivity1, true);
        $repository->save($futureActivity2, true);

        $upcomingActivities = $repository->findUpcomingActivities(5);

        // 验证我们创建的未来活动都在结果中
        $upcomingIds = array_map(fn ($activity) => $activity->getId(), $upcomingActivities);
        $this->assertContains($futureActivity1->getId(), $upcomingIds, 'Future activity 1 should be in results');
        $this->assertContains($futureActivity2->getId(), $upcomingIds, 'Future activity 2 should be in results');

        // 验证当前活动不在结果中
        $this->assertNotContains($currentActivity->getId(), $upcomingIds, 'Current activity should not be in upcoming results');

        // 验证所有返回的活动都是有效且未来的
        $now = CarbonImmutable::now();
        foreach ($upcomingActivities as $activity) {
            $this->assertTrue($activity->isValid(), 'All upcoming activities should be valid');
            $this->assertGreaterThan($now, $activity->getStartTime(), 'All upcoming activities should start in the future');
        }
    }

    public function testFindUpcomingActivitiesWithLimitShouldRespectMaxResults(): void
    {
        $now = CarbonImmutable::now();
        $repository = $this->getRepository();

        for ($i = 1; $i <= 5; ++$i) {
            $activity = $this->createValidActivity();
            $activity->setStartTime($now->addDays($i));
            $activity->setEndTime($now->addDays($i + 1));
            $activity->setValid(true);
            $repository->save($activity, true);
        }

        $upcomingActivities = $repository->findUpcomingActivities(3);

        $this->assertCount(3, $upcomingActivities);
    }

    public function testFindEndedActivitiesShouldReturnPastValidActivities(): void
    {
        $now = CarbonImmutable::now();

        $currentActivity = $this->createValidActivity();
        $currentActivity->setStartTime($now->subDay());
        $currentActivity->setEndTime($now->addDay());
        $currentActivity->setValid(true);

        $endedActivity1 = $this->createValidActivity();
        $endedActivity1->setStartTime($now->subDays(3));
        $endedActivity1->setEndTime($now->subDay());
        $endedActivity1->setValid(true);

        $endedActivity2 = $this->createValidActivity();
        $endedActivity2->setStartTime($now->subDays(5));
        $endedActivity2->setEndTime($now->subDays(4));
        $endedActivity2->setValid(true);

        $repository = $this->getRepository();
        $repository->save($currentActivity, true);
        $repository->save($endedActivity1, true);
        $repository->save($endedActivity2, true);

        $endedActivities = $repository->findEndedActivities(5);

        $this->assertCount(2, $endedActivities);
        $this->assertEquals($endedActivity1->getId(), $endedActivities[0]->getId());
        $this->assertEquals($endedActivity2->getId(), $endedActivities[1]->getId());
    }

    public function testFindEndedActivitiesWithLimitShouldRespectMaxResults(): void
    {
        $now = CarbonImmutable::now();
        $repository = $this->getRepository();

        for ($i = 1; $i <= 5; ++$i) {
            $activity = $this->createValidActivity();
            $activity->setStartTime($now->subDays($i + 1));
            $activity->setEndTime($now->subDays($i));
            $activity->setValid(true);
            $repository->save($activity, true);
        }

        $endedActivities = $repository->findEndedActivities(3);

        $this->assertCount(3, $endedActivities);
    }

    private function createValidActivity(): Activity
    {
        return $this->createNewEntity();
    }
}
