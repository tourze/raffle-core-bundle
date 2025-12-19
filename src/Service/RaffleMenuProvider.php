<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\RaffleCoreBundle\Controller\Admin\ActivityCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\AwardCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\ChanceCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\PoolCrudController;

/**
 * 抽奖管理菜单提供者
 */
#[Autoconfigure(public: true)]
final readonly class RaffleMenuProvider implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        $raffleMenu = $item->addChild('抽奖管理', [
            'uri' => '#',
            'attributes' => [
                'icon' => 'fa fa-gift',
            ],
        ]);

        $raffleMenu->addChild('抽奖活动管理')
            ->setUri($this->linkGenerator->getCurdListPage(ActivityCrudController::class))
            ->setAttribute('icon', 'fa fa-calendar-alt')
        ;

        $raffleMenu->addChild('奖池管理')
            ->setUri($this->linkGenerator->getCurdListPage(PoolCrudController::class))
            ->setAttribute('icon', 'fa fa-swimming-pool')
        ;

        $raffleMenu->addChild('奖品管理')
            ->setUri($this->linkGenerator->getCurdListPage(AwardCrudController::class))
            ->setAttribute('icon', 'fa fa-trophy')
        ;

        $raffleMenu->addChild('抽奖记录管理')
            ->setUri($this->linkGenerator->getCurdListPage(ChanceCrudController::class))
            ->setAttribute('icon', 'fa fa-history')
        ;
    }
}
