<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ProductCoreBundle\DataFixtures\SkuFixtures;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Pool;

#[When(env: 'test')]
#[When(env: 'dev')]
final class AwardFixtures extends Fixture implements DependentFixtureInterface
{
    public const AWARD_GRAND_PRIZE = 'award_grand_prize';
    public const AWARD_SECOND_PRIZE = 'award_second_prize';
    public const AWARD_THIRD_PRIZE = 'award_third_prize';
    public const AWARD_CONSOLATION = 'award_consolation';

    public function load(ObjectManager $manager): void
    {
        $mainPool = $this->getReference(PoolFixtures::POOL_MAIN_PRIZES, Pool::class);
        $consolationPool = $this->getReference(PoolFixtures::POOL_CONSOLATION, Pool::class);
        $testSku = $this->getReference(SkuFixtures::TEST_SKU_REFERENCE, Sku::class);

        // 特等奖
        $grandPrize = new Award();
        $grandPrize->setName('特等奖 - iPhone 15 Pro Max');
        $grandPrize->setDescription('顶级智能手机，256GB存储空间');
        $grandPrize->setPool($mainPool);
        $grandPrize->setSku($testSku);
        $grandPrize->setProbability(10);
        $grandPrize->setQuantity(5);
        $grandPrize->setDayLimit(2);
        $grandPrize->setAmount(1);
        $grandPrize->setValue('1999.00');
        $grandPrize->setNeedConsignee(true);
        $grandPrize->setValid(true);
        $grandPrize->setSortNumber(1);
        $manager->persist($grandPrize);
        $this->addReference(self::AWARD_GRAND_PRIZE, $grandPrize);

        // 二等奖
        $secondPrize = new Award();
        $secondPrize->setName('二等奖 - AirPods Pro');
        $secondPrize->setDescription('苹果无线降噪耳机');
        $secondPrize->setPool($mainPool);
        $secondPrize->setSku($testSku);
        $secondPrize->setProbability(50);
        $secondPrize->setQuantity(20);
        $secondPrize->setDayLimit(5);
        $secondPrize->setAmount(1);
        $secondPrize->setValue('599.00');
        $secondPrize->setNeedConsignee(true);
        $secondPrize->setValid(true);
        $secondPrize->setSortNumber(2);
        $manager->persist($secondPrize);
        $this->addReference(self::AWARD_SECOND_PRIZE, $secondPrize);

        // 三等奖
        $thirdPrize = new Award();
        $thirdPrize->setName('三等奖 - 小米移动电源');
        $thirdPrize->setDescription('10000mAh快充移动电源');
        $thirdPrize->setPool($mainPool);
        $thirdPrize->setSku($testSku);
        $thirdPrize->setProbability(200);
        $thirdPrize->setQuantity(100);
        $thirdPrize->setDayLimit(20);
        $thirdPrize->setAmount(1);
        $thirdPrize->setValue('99.00');
        $thirdPrize->setNeedConsignee(false);
        $thirdPrize->setValid(true);
        $thirdPrize->setSortNumber(3);
        $manager->persist($thirdPrize);
        $this->addReference(self::AWARD_THIRD_PRIZE, $thirdPrize);

        // 安慰奖
        $consolationPrize = new Award();
        $consolationPrize->setName('安慰奖 - 优惠券');
        $consolationPrize->setDescription('5元购物优惠券');
        $consolationPrize->setPool($consolationPool);
        $consolationPrize->setSku($testSku);
        $consolationPrize->setProbability(5000);
        $consolationPrize->setQuantity(1000);
        $consolationPrize->setAmount(1);
        $consolationPrize->setValue('5.00');
        $consolationPrize->setNeedConsignee(false);
        $consolationPrize->setValid(true);
        $consolationPrize->setSortNumber(99);
        $manager->persist($consolationPrize);
        $this->addReference(self::AWARD_CONSOLATION, $consolationPrize);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            PoolFixtures::class,
            SkuFixtures::class,
        ];
    }
}
