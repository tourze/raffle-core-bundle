<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\RaffleCoreBundle\Exception\InvalidPrizeException;

/**
 * @internal
 */
#[CoversClass(InvalidPrizeException::class)]
final class InvalidPrizeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidPrizeException('test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('test message', $exception->getMessage());
    }
}
