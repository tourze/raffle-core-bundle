<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ProductCoreBundle\DataFixtures\SkuFixtures;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Pool;

#[When(env: 'test')]
#[When(env: 'dev')]
final class PoolFixtures extends Fixture implements DependentFixtureInterface
{
    public const POOL_MAIN_PRIZES = 'pool_main_prizes';
    public const POOL_CONSOLATION = 'pool_consolation';

    public function load(ObjectManager $manager): void
    {
        $activeActivity = $this->getReference(ActivityFixtures::ACTIVITY_ACTIVE, Activity::class);

        // 创建主奖池
        $mainPool = new Pool();
        $mainPool->setName('主要奖品奖池');
        $mainPool->setDescription('包含特等奖、二等奖、三等奖的主要奖池');
        $mainPool->setIsDefault(false);
        $mainPool->setValid(true);
        $mainPool->setSortNumber(1);
        $mainPool->addActivity($activeActivity);
        $manager->persist($mainPool);
        $this->addReference(self::POOL_MAIN_PRIZES, $mainPool);

        // 创建兜底奖池
        $consolationPool = new Pool();
        $consolationPool->setName('兜底奖池');
        $consolationPool->setDescription('包含安慰奖的兜底奖池');
        $consolationPool->setIsDefault(true);
        $consolationPool->setValid(true);
        $consolationPool->setSortNumber(99);
        $consolationPool->addActivity($activeActivity);
        $manager->persist($consolationPool);
        $this->addReference(self::POOL_CONSOLATION, $consolationPool);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ActivityFixtures::class,
        ];
    }
}
