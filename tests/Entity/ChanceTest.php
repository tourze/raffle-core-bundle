<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Entity;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;

/**
 * @internal
 */
#[CoversClass(Chance::class)]
final class ChanceTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new Chance();
    }

    /**
     * @return array<int, array{string, mixed}>
     */
    public static function propertiesProvider(): array
    {
        return [
            ['status', ChanceStatusEnum::INIT],
            ['status', ChanceStatusEnum::WINNING],
            ['status', ChanceStatusEnum::ORDERED],
            ['status', ChanceStatusEnum::EXPIRED],
            ['winContext', ['test' => 'value']],
            ['lockVersion', 1],
        ];
    }

    public function testChanceCreation(): void
    {
        $chance = new Chance();

        $this->assertEquals(ChanceStatusEnum::INIT, $chance->getStatus());
        $this->assertNull($chance->getAward());
        $this->assertFalse($chance->isWinning());
        $this->assertFalse($chance->canOrder());
    }

    public function testMarkAsWinning(): void
    {
        $chance = new Chance();
        $award = new Award();
        $context = ['prize_name' => 'iPhone 15', 'probability' => 0.1];

        $chance->markAsWinning($award, $context);

        $this->assertEquals(ChanceStatusEnum::WINNING, $chance->getStatus());
        $this->assertSame($award, $chance->getAward());
        $this->assertEquals($context, $chance->getWinContext());
        $this->assertTrue($chance->isWinning());
        $this->assertTrue($chance->canOrder());
        $this->assertInstanceOf(CarbonImmutable::class, $chance->getUseTime());
    }

    public function testMarkAsOrdered(): void
    {
        $chance = new Chance();
        $award = new Award();

        // 先设置为中奖状态
        $chance->markAsWinning($award);

        // 然后下单
        $chance->markAsOrdered();

        $this->assertEquals(ChanceStatusEnum::ORDERED, $chance->getStatus());
        $this->assertFalse($chance->canOrder()); // 已下单不能再下单
    }

    public function testMarkAsExpired(): void
    {
        $chance = new Chance();
        $chance->markAsExpired();

        $this->assertEquals(ChanceStatusEnum::EXPIRED, $chance->getStatus());
        $this->assertTrue($chance->isExpired());
    }

    public function testCanOrderOnlyWhenWinning(): void
    {
        $chance = new Chance();
        $award = new Award();

        // 初始状态不能下单
        $this->assertFalse($chance->canOrder());

        // 中奖后可以下单
        $chance->markAsWinning($award);
        $this->assertTrue($chance->canOrder());

        // 已下单后不能再下单
        $chance->markAsOrdered();
        $this->assertFalse($chance->canOrder());

        // 过期后不能下单
        $chance->markAsExpired();
        $this->assertFalse($chance->canOrder());
    }
}
