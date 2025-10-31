<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Entity\Pool;

/**
 * @internal
 */
#[CoversClass(Award::class)]
final class AwardTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new Award();
    }

    /**
     * @return array<int, array{string, mixed}>
     */
    public static function propertiesProvider(): array
    {
        return [
            ['name', 'Test Award'],
            ['description', 'Test Award Description'],
            ['probability', 50],
            ['quantity', 100],
            ['dayLimit', 10],
            ['amount', 2],
            ['value', '99.99'],
            ['needConsignee', true],
            ['needConsignee', false],
            ['valid', true],
            ['valid', false],
            ['sortNumber', 10],
        ];
    }

    public function testAwardCreation(): void
    {
        $award = new Award();

        $this->assertEquals('', $award->getName());
        $this->assertNull($award->getDescription());
        $this->assertEquals(0, $award->getProbability());
        $this->assertEquals(0, $award->getQuantity());
        $this->assertEquals(1, $award->getAmount());
        $this->assertEquals('0.00', $award->getValue());
        $this->assertFalse($award->isNeedConsignee());
        $this->assertTrue($award->isValid());
        $this->assertEquals(0, $award->getSortNumber());
        $this->assertNull($award->getDayLimit());
        $this->assertEmpty($award->getChances());
    }

    public function testDecreaseQuantity(): void
    {
        $award = new Award();
        $award->setQuantity(10);

        // 减少数量成功
        $result = $award->decreaseQuantity(3);
        $this->assertTrue($result);
        $this->assertEquals(7, $award->getQuantity());

        // 减少数量超出库存
        $result = $award->decreaseQuantity(10);
        $this->assertFalse($result);
        $this->assertEquals(7, $award->getQuantity());
    }

    public function testDecreaseQuantityByOne(): void
    {
        $award = new Award();
        $award->setQuantity(5);

        $result = $award->decreaseQuantity();
        $this->assertTrue($result);
        $this->assertEquals(4, $award->getQuantity());
    }

    public function testStringRepresentation(): void
    {
        $pool = new Pool();
        $pool->setName('测试奖池');

        $award = new Award();
        $award->setName('测试奖品');
        $award->setPool($pool);

        $expectedString = '测试奖品';
        $this->assertEquals($expectedString, (string) $award);
    }

    public function testStringRepresentationWithoutPool(): void
    {
        $award = new Award();
        $award->setName('测试奖品');

        $expectedString = '测试奖品';
        $this->assertEquals($expectedString, (string) $award);
    }

    public function testSettersAndGetters(): void
    {
        $award = new Award();

        $award->setName('Test Award');
        $this->assertEquals('Test Award', $award->getName());

        $award->setDescription('Award description');
        $this->assertEquals('Award description', $award->getDescription());

        $award->setProbability(50);
        $this->assertEquals(50, $award->getProbability());

        $award->setQuantity(100);
        $this->assertEquals(100, $award->getQuantity());

        $award->setDayLimit(10);
        $this->assertEquals(10, $award->getDayLimit());

        $award->setAmount(2);
        $this->assertEquals(2, $award->getAmount());

        $award->setValue('99.99');
        $this->assertEquals('99.99', $award->getValue());

        $award->setNeedConsignee(true);
        $this->assertTrue($award->isNeedConsignee());

        $award->setValid(false);
        $this->assertFalse($award->isValid());

        $award->setSortNumber(10);
        $this->assertEquals(10, $award->getSortNumber());
    }

    public function testPoolRelationship(): void
    {
        $award = new Award();
        $pool = new Pool();
        $pool->setName('Test Pool');

        $award->setPool($pool);
        $this->assertEquals($pool, $award->getPool());
    }

    public function testSkuRelationship(): void
    {
        $award = new Award();
        $sku = new Sku();
        $sku->setGtin('TEST-SKU');

        $award->setSku($sku);
        $this->assertEquals($sku, $award->getSku());
    }

    public function testAddChance(): void
    {
        $award = new Award();
        $chance = new Chance();

        $award->addChance($chance);

        $this->assertTrue($award->getChances()->contains($chance));
        $this->assertEquals($award, $chance->getAward());
    }

    public function testRemoveChance(): void
    {
        $award = new Award();
        $chance = new Chance();

        $award->addChance($chance);
        $this->assertTrue($award->getChances()->contains($chance));

        $award->removeChance($chance);
        $this->assertFalse($award->getChances()->contains($chance));
        $this->assertNull($chance->getAward());
    }

    public function testDefaultValues(): void
    {
        $award = new Award();

        $this->assertEquals('', $award->getName());
        $this->assertNull($award->getDescription());
        $this->assertNull($award->getPool());
        $this->assertNull($award->getSku());
        $this->assertNull($award->getDayLimit());
        $this->assertEquals(0, $award->getProbability());
        $this->assertEquals(0, $award->getQuantity());
        $this->assertEquals(1, $award->getAmount());
        $this->assertEquals('0.00', $award->getValue());
        $this->assertFalse($award->isNeedConsignee());
        $this->assertTrue($award->isValid());
        $this->assertEquals(0, $award->getSortNumber());
        $this->assertEmpty($award->getChances());
    }
}
