<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\DataFixtures;

use Carbon\CarbonImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\UserServiceContracts\UserManagerInterface;

#[When(env: 'test')]
#[When(env: 'dev')]
class ChanceFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
    ) {
    }
    public const CHANCE_WINNING = 'chance_winning';
    public const CHANCE_INIT = 'chance_init';
    public const CHANCE_ORDERED = 'chance_ordered';
    public const CHANCE_EXPIRED = 'chance_expired';

    public function load(ObjectManager $manager): void
    {
        $activeActivity = $this->getReference(ActivityFixtures::ACTIVITY_ACTIVE, Activity::class);
        $grandPrizeAward = $this->getReference(AwardFixtures::AWARD_GRAND_PRIZE, Award::class);
        $consolationAward = $this->getReference(AwardFixtures::AWARD_CONSOLATION, Award::class);

        $adminUser = $this->userManager->createUser('admin@test.local', 'Admin User');
        $manager->persist($adminUser);
        $normalUser1 = $this->userManager->createUser('user1@test.local', 'Normal User 1');
        $manager->persist($normalUser1);
        $normalUser2 = $this->userManager->createUser('user2@test.local', 'Normal User 2');
        $manager->persist($normalUser2);
        $normalUser3 = $this->userManager->createUser('user3@test.local', 'Normal User 3');
        $manager->persist($normalUser3);

        $winningChance = new Chance();
        $winningChance->setActivity($activeActivity);
        $winningChance->setUser($adminUser);
        $winningChance->setStatus(ChanceStatusEnum::WINNING);
        $winningChance->setUseTime(CarbonImmutable::now()->subHours(2));
        $winningChance->setAward($grandPrizeAward);
        $winningChance->setWinContext([
            'prize_name' => '超级大奖',
            'prize_value' => '1999.00',
            'win_time' => CarbonImmutable::now()->subHours(2)->toDateTimeString(),
        ]);
        $manager->persist($winningChance);
        $this->addReference(self::CHANCE_WINNING, $winningChance);

        $initChance = new Chance();
        $initChance->setActivity($activeActivity);
        $initChance->setUser($normalUser1);
        $initChance->setStatus(ChanceStatusEnum::INIT);
        $manager->persist($initChance);
        $this->addReference(self::CHANCE_INIT, $initChance);

        $orderedChance = new Chance();
        $orderedChance->setActivity($activeActivity);
        $orderedChance->setUser($normalUser2);
        $orderedChance->setStatus(ChanceStatusEnum::ORDERED);
        $orderedChance->setUseTime(CarbonImmutable::now()->subDays(1));
        $orderedChance->setAward($consolationAward);
        $orderedChance->setWinContext([
            'prize_name' => '安慰奖',
            'prize_value' => '5.00',
            'win_time' => CarbonImmutable::now()->subDays(1)->toDateTimeString(),
            'order_id' => 'ORDER_' . time(),
        ]);
        $manager->persist($orderedChance);
        $this->addReference(self::CHANCE_ORDERED, $orderedChance);

        $expiredChance = new Chance();
        $expiredChance->setActivity($activeActivity);
        $expiredChance->setUser($normalUser3);
        $expiredChance->setStatus(ChanceStatusEnum::EXPIRED);
        $expiredChance->setUseTime(CarbonImmutable::now()->subDays(5));
        $expiredChance->setAward($consolationAward);
        $expiredChance->setWinContext([
            'prize_name' => '安慰奖',
            'prize_value' => '5.00',
            'win_time' => CarbonImmutable::now()->subDays(5)->toDateTimeString(),
            'expire_reason' => '超时未领取',
        ]);
        $manager->persist($expiredChance);
        $this->addReference(self::CHANCE_EXPIRED, $expiredChance);

        for ($i = 4; $i <= 10; ++$i) {
            $user = $this->userManager->createUser("user{$i}@test.local", "Normal User {$i}");
            $manager->persist($user);
            $chance = new Chance();
            $chance->setActivity($activeActivity);
            $chance->setUser($user);
            $chance->setStatus(ChanceStatusEnum::INIT);
            $manager->persist($chance);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ActivityFixtures::class,
            AwardFixtures::class,
        ];
    }
}
