<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\RaffleCoreBundle\Exception\ActivityInactiveException;

/**
 * @internal
 */
#[CoversClass(ActivityInactiveException::class)]
final class ActivityInactiveExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new ActivityInactiveException('test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('test message', $exception->getMessage());
    }
}
