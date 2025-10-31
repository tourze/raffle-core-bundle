<?php

namespace Tourze\RaffleCoreBundle;

use DeliverOrderBundle\DeliverOrderBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use OrderCoreBundle\OrderCoreBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\ProductCoreBundle\ProductCoreBundle;
use Tourze\StockManageBundle\StockManageBundle;

class RaffleCoreBundle extends Bundle implements BundleDependencyInterface
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
        ];
    }
}
