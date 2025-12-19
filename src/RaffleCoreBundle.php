<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle;

use DeliverOrderBundle\DeliverOrderBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use OrderCoreBundle\OrderCoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;
use Tourze\ProductCoreBundle\ProductCoreBundle;
use Tourze\StockManageBundle\StockManageBundle;

final class RaffleCoreBundle extends AbstractBundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            DoctrineFixturesBundle::class => ['all' => true],
            ProductCoreBundle::class => ['all' => true],
            OrderCoreBundle::class => ['all' => true],
            DeliverOrderBundle::class => ['all' => true],
            StockManageBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            DoctrineIndexedBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('Resources/config/services.yaml');

        // 加载环境特定的服务配置
        $env = $builder->getParameter('kernel.environment');
        if (!is_string($env)) {
            return;
        }

        match ($env) {
            'test' => $container->import('Resources/config/services_test.yaml'),
            default => null,
        };
    }
}
