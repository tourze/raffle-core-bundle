<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Entity;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;

/**
 * @internal
 */
#[CoversClass(Activity::class)]
final class ActivityTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new Activity();
    }

    /**
     * @return array<int, array{string, mixed}>
     */
    public static function propertiesProvider(): array
    {
        return [
            ['title', 'Test Activity Title'],
            ['description', 'Test Activity Description'],
            ['picture', 'https://example.com/image.jpg'],
            ['valid', true],
            ['valid', false],
        ];
    }

    public function testActivityCreation(): void
    {
        $startTime = CarbonImmutable::now();
        $endTime = $startTime->addDays(7);

        $activity = new Activity();
        $activity->setTitle('测试活动');
        $activity->setDescription('这是一个测试活动');
        $activity->setStartTime($startTime);
        $activity->setEndTime($endTime);

        $this->assertEquals('测试活动', $activity->getTitle());
        $this->assertEquals('这是一个测试活动', $activity->getDescription());
        $this->assertEquals($startTime, $activity->getStartTime());
        $this->assertEquals($endTime, $activity->getEndTime());
        $this->assertTrue($activity->isValid());
    }

    public function testActivityIsActive(): void
    {
        $activity = new Activity();
        $activity->setTitle('当前活动');
        $activity->setStartTime(CarbonImmutable::now()->subHour());
        $activity->setEndTime(CarbonImmutable::now()->addHour());
        $activity->setValid(true);

        $this->assertTrue($activity->isActive());
    }

    public function testActivityIsNotActiveWhenExpired(): void
    {
        $activity = new Activity();
        $activity->setTitle('过期活动');
        $activity->setStartTime(CarbonImmutable::now()->subDays(2));
        $activity->setEndTime(CarbonImmutable::now()->subDay());
        $activity->setValid(true);

        $this->assertFalse($activity->isActive());
    }

    public function testActivityIsNotActiveWhenNotStarted(): void
    {
        $activity = new Activity();
        $activity->setTitle('未开始活动');
        $activity->setStartTime(CarbonImmutable::now()->addHour());
        $activity->setEndTime(CarbonImmutable::now()->addDays(7));
        $activity->setValid(true);

        $this->assertFalse($activity->isActive());
    }

    public function testActivityIsNotActiveWhenInvalid(): void
    {
        $activity = new Activity();
        $activity->setTitle('无效活动');
        $activity->setStartTime(CarbonImmutable::now()->subHour());
        $activity->setEndTime(CarbonImmutable::now()->addHour());
        $activity->setValid(false);

        $this->assertFalse($activity->isActive());
    }
}
