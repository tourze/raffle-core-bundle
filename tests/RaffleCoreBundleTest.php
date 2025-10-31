<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests;

use DeliverOrderBundle\DeliverOrderBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use OrderCoreBundle\OrderCoreBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\ProductCoreBundle\ProductCoreBundle;
use Tourze\RaffleCoreBundle\RaffleCoreBundle;
use Tourze\StockManageBundle\StockManageBundle;

/**
 * @internal
 */
#[CoversClass(RaffleCoreBundle::class)]
#[RunTestsInSeparateProcesses]
final class RaffleCoreBundleTest extends AbstractBundleTestCase
{
    public function testBundleShouldImplementBundleDependencyInterface(): void
    {
        $reflection = new \ReflectionClass(RaffleCoreBundle::class);
        $this->assertTrue($reflection->implementsInterface(BundleDependencyInterface::class));
    }

    public function testBundleShouldExtendSymfonyBundle(): void
    {
        $reflection = new \ReflectionClass(RaffleCoreBundle::class);
        $this->assertTrue($reflection->isSubclassOf(Bundle::class));
    }

    public function testBundleDependenciesShouldIncludeAllRequiredBundles(): void
    {
        $dependencies = RaffleCoreBundle::getBundleDependencies();

        $this->assertArrayHasKey(DoctrineBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineFixturesBundle::class, $dependencies);
        $this->assertArrayHasKey(ProductCoreBundle::class, $dependencies);
        $this->assertArrayHasKey(OrderCoreBundle::class, $dependencies);
        $this->assertArrayHasKey(DeliverOrderBundle::class, $dependencies);
        $this->assertArrayHasKey(StockManageBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineSnowflakeBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineTimestampBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineIndexedBundle::class, $dependencies);
    }

    public function testBundleDependenciesShouldHaveCorrectEnvironmentConfiguration(): void
    {
        $dependencies = RaffleCoreBundle::getBundleDependencies();

        foreach ($dependencies as $bundleClass => $environments) {
            $this->assertArrayHasKey('all', $environments, "Bundle {$bundleClass} should have 'all' environment key");
            $this->assertTrue($environments['all'], "Bundle {$bundleClass} should be enabled in 'all' environments");
        }
    }
}
