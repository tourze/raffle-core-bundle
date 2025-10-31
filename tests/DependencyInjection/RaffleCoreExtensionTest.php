<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\RaffleCoreBundle\DependencyInjection\RaffleCoreExtension;
use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

/**
 * @internal
 */
#[CoversClass(RaffleCoreExtension::class)]
final class RaffleCoreExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    public function testExtensionShouldInheritFromAutoExtension(): void
    {
        $extension = new RaffleCoreExtension();

        $this->assertInstanceOf(AutoExtension::class, $extension);
    }

    public function testGetConfigDirShouldReturnCorrectPath(): void
    {
        $extension = new RaffleCoreExtension();
        $reflection = new \ReflectionClass($extension);
        $method = $reflection->getMethod('getConfigDir');
        $method->setAccessible(true);

        $configDir = $method->invoke($extension);
        $this->assertIsString($configDir);

        $normalizedPath = realpath($configDir);
        $this->assertNotFalse($normalizedPath);
        $this->assertStringContainsString('Resources/config', $normalizedPath);
        $this->assertStringEndsWith('Resources/config', $normalizedPath);
    }

    public function testExtensionAliasShouldBeCorrect(): void
    {
        $extension = new RaffleCoreExtension();

        $alias = $extension->getAlias();

        $this->assertEquals('raffle_core', $alias);
    }
}
