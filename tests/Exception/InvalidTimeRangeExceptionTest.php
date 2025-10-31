<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\RaffleCoreBundle\Exception\InvalidTimeRangeException;

/**
 * @internal
 */
#[CoversClass(InvalidTimeRangeException::class)]
final class InvalidTimeRangeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidTimeRangeException('test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('test message', $exception->getMessage());
    }
}
