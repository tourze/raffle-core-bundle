<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;

/**
 * @internal
 */
#[CoversClass(ChanceStatusEnum::class)]
final class ChanceStatusEnumTest extends AbstractEnumTestCase
{
    public function testToArray(): void
    {
        $result = ChanceStatusEnum::INIT->toArray();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertEquals('init', $result['value']);
        $this->assertArrayHasKey('label', $result);
        $this->assertEquals('初始化', $result['label']);
    }
}
