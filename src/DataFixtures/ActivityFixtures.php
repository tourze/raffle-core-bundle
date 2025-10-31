<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\DataFixtures;

use Carbon\CarbonImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\RaffleCoreBundle\Entity\Activity;

#[When(env: 'test')]
#[When(env: 'dev')]
class ActivityFixtures extends Fixture
{
    public const ACTIVITY_ACTIVE = 'activity_active';
    public const ACTIVITY_UPCOMING = 'activity_upcoming';
    public const ACTIVITY_EXPIRED = 'activity_expired';

    public function load(ObjectManager $manager): void
    {
        $now = CarbonImmutable::now();

        $activeActivity = new Activity();
        $activeActivity->setTitle('春节大抽奖活动');
        $activeActivity->setDescription('欢庆春节，参与抽奖赢大奖！丰富奖品等你来拿，每日限量抽奖机会。');
        $activeActivity->setPicture('https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800&h=600&fit=crop');
        $activeActivity->setStartTime($now->subDays(2));
        $activeActivity->setEndTime($now->addDays(5));
        $activeActivity->setValid(true);
        $manager->persist($activeActivity);
        $this->addReference(self::ACTIVITY_ACTIVE, $activeActivity);

        $upcomingActivity = new Activity();
        $upcomingActivity->setTitle('元宵节特别活动');
        $upcomingActivity->setDescription('元宵佳节，团团圆圆！特别准备了精美礼品，快来参与吧！');
        $upcomingActivity->setPicture('https://images.unsplash.com/photo-1612198228254-f204d220d325?w=800&h=600&fit=crop');
        $upcomingActivity->setStartTime($now->addDays(10));
        $upcomingActivity->setEndTime($now->addDays(17));
        $upcomingActivity->setValid(true);
        $manager->persist($upcomingActivity);
        $this->addReference(self::ACTIVITY_UPCOMING, $upcomingActivity);

        $expiredActivity = new Activity();
        $expiredActivity->setTitle('新年礼品大放送');
        $expiredActivity->setDescription('新年到，礼品到！感谢大家的参与，活动已结束。');
        $expiredActivity->setPicture('https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&h=600&fit=crop');
        $expiredActivity->setStartTime($now->subDays(30));
        $expiredActivity->setEndTime($now->subDays(15));
        $expiredActivity->setValid(false);
        $manager->persist($expiredActivity);
        $this->addReference(self::ACTIVITY_EXPIRED, $expiredActivity);

        $manager->flush();
    }
}
