<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Pool;

/**
 * @internal
 */
#[CoversClass(Pool::class)]
final class PoolTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new Pool();
    }

    /**
     * @return array<int, array{string, mixed}>
     */
    public static function propertiesProvider(): array
    {
        return [
            ['name', 'Test Pool'],
            ['description', 'Test Pool Description'],
            ['probabilityExpression', 'user.level * 0.1'],
            ['valid', true],
            ['valid', false],
            ['sortNumber', 10],
        ];
    }

    public function testPoolCreation(): void
    {
        $pool = new Pool();

        $this->assertEquals('', $pool->getName());
        $this->assertNull($pool->getDescription());
        $this->assertFalse($pool->isDefault());
        $this->assertTrue($pool->isValid());
        $this->assertEquals(0, $pool->getSortNumber());
        $this->assertEmpty($pool->getActivities());
        $this->assertEmpty($pool->getAwards());
    }

    public function testSettersAndGetters(): void
    {
        $pool = new Pool();

        $pool->setName('Test Pool');
        $this->assertEquals('Test Pool', $pool->getName());

        $pool->setDescription('Pool description');
        $this->assertEquals('Pool description', $pool->getDescription());

        $pool->setIsDefault(true);
        $this->assertTrue($pool->isDefault());

        $pool->setValid(false);
        $this->assertFalse($pool->isValid());

        $pool->setSortNumber(10);
        $this->assertEquals(10, $pool->getSortNumber());
    }

    public function testAddActivity(): void
    {
        $pool = new Pool();
        $activity = new Activity();
        $activity->setTitle('Test Activity');

        $pool->addActivity($activity);

        $this->assertTrue($pool->getActivities()->contains($activity));
        $this->assertTrue($activity->getPools()->contains($pool));
    }

    public function testRemoveActivity(): void
    {
        $pool = new Pool();
        $activity = new Activity();
        $activity->setTitle('Test Activity');

        $pool->addActivity($activity);
        $this->assertTrue($pool->getActivities()->contains($activity));

        $pool->removeActivity($activity);
        $this->assertFalse($pool->getActivities()->contains($activity));
        $this->assertFalse($activity->getPools()->contains($pool));
    }

    public function testAddAward(): void
    {
        $pool = new Pool();
        $award = new Award();
        $award->setName('Test Award');

        $pool->addAward($award);

        $this->assertTrue($pool->getAwards()->contains($award));
        $this->assertEquals($pool, $award->getPool());
    }

    public function testRemoveAward(): void
    {
        $pool = new Pool();
        $award = new Award();
        $award->setName('Test Award');

        $pool->addAward($award);
        $this->assertTrue($pool->getAwards()->contains($award));

        $pool->removeAward($award);
        $this->assertFalse($pool->getAwards()->contains($award));
        $this->assertNull($award->getPool());
    }

    public function testStringRepresentation(): void
    {
        $pool = new Pool();
        $pool->setName('Test Pool');

        $this->assertEquals('Test Pool', (string) $pool);
    }

    public function testStringRepresentationWithoutName(): void
    {
        $pool = new Pool();

        $this->assertEquals('未命名奖池', (string) $pool);
    }

    public function testDefaultValues(): void
    {
        $pool = new Pool();

        $this->assertEquals('', $pool->getName());
        $this->assertNull($pool->getDescription());
        $this->assertFalse($pool->isDefault());
        $this->assertTrue($pool->isValid());
        $this->assertEquals(0, $pool->getSortNumber());
        $this->assertEmpty($pool->getActivities());
        $this->assertEmpty($pool->getAwards());
    }
}
