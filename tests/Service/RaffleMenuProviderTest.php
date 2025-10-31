<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use Tourze\RaffleCoreBundle\Controller\Admin\ActivityCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\AwardCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\ChanceCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\PoolCrudController;
use Tourze\RaffleCoreBundle\Service\RaffleMenuProvider;

/**
 * @internal
 */
#[CoversClass(RaffleMenuProvider::class)]
#[RunTestsInSeparateProcesses]
final class RaffleMenuProviderTest extends AbstractEasyAdminMenuTestCase
{
    protected function onSetUp(): void
    {
    }

    public function getMenuProviderServiceClass(): string
    {
        return RaffleMenuProvider::class;
    }

    public function testInvokeWithMenuItemShouldCreateRaffleMenuStructure(): void
    {
        $linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $linkGenerator
            ->expects($this->exactly(4))
            ->method('getCurdListPage')
            ->willReturnMap([
                [ActivityCrudController::class, '/admin/activity'],
                [PoolCrudController::class, '/admin/pool'],
                [AwardCrudController::class, '/admin/award'],
                [ChanceCrudController::class, '/admin/chance'],
            ])
        ;

        self::getContainer()->set(LinkGeneratorInterface::class, $linkGenerator);
        $menuProvider = self::getService(RaffleMenuProvider::class);

        $childMenu1 = $this->createMock(ItemInterface::class);
        $childMenu2 = $this->createMock(ItemInterface::class);
        $childMenu3 = $this->createMock(ItemInterface::class);
        $childMenu4 = $this->createMock(ItemInterface::class);
        $raffleMenu = $this->createMock(ItemInterface::class);

        $parentMenuItem = $this->createMock(ItemInterface::class);
        $parentMenuItem
            ->expects($this->once())
            ->method('addChild')
            ->with('抽奖管理', [
                'uri' => '#',
                'attributes' => [
                    'icon' => 'fa fa-gift',
                ],
            ])
            ->willReturn($raffleMenu)
        ;

        $raffleMenu
            ->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnOnConsecutiveCalls($childMenu1, $childMenu2, $childMenu3, $childMenu4)
        ;

        // 配置链式调用：addChild()->setUri()->setAttribute()
        $childMenu1
            ->expects($this->once())
            ->method('setUri')
            ->with('/admin/activity')
            ->willReturn($childMenu1) // 支持链式调用
        ;
        $childMenu1
            ->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fa fa-calendar-alt')
            ->willReturn($childMenu1)
        ;

        $childMenu2
            ->expects($this->once())
            ->method('setUri')
            ->with('/admin/pool')
            ->willReturn($childMenu2) // 支持链式调用
        ;
        $childMenu2
            ->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fa fa-swimming-pool')
            ->willReturn($childMenu2)
        ;

        $childMenu3
            ->expects($this->once())
            ->method('setUri')
            ->with('/admin/award')
            ->willReturn($childMenu3) // 支持链式调用
        ;
        $childMenu3
            ->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fa fa-trophy')
            ->willReturn($childMenu3)
        ;

        $childMenu4
            ->expects($this->once())
            ->method('setUri')
            ->with('/admin/chance')
            ->willReturn($childMenu4) // 支持链式调用
        ;
        $childMenu4
            ->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fa fa-history')
            ->willReturn($childMenu4)
        ;

        $menuProvider->__invoke($parentMenuItem);
    }

    public function testMenuProviderShouldCreateCorrectMenuLabels(): void
    {
        $linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $linkGenerator
            ->method('getCurdListPage')
            ->willReturn('/mock-url')
        ;

        self::getContainer()->set(LinkGeneratorInterface::class, $linkGenerator);
        $menuProvider = self::getService(RaffleMenuProvider::class);

        $childMenu1 = $this->createMock(ItemInterface::class);
        $childMenu1->method('setUri')->willReturn($childMenu1);
        $childMenu1->method('setAttribute')->willReturn($childMenu1);

        $childMenu2 = $this->createMock(ItemInterface::class);
        $childMenu2->method('setUri')->willReturn($childMenu2);
        $childMenu2->method('setAttribute')->willReturn($childMenu2);

        $childMenu3 = $this->createMock(ItemInterface::class);
        $childMenu3->method('setUri')->willReturn($childMenu3);
        $childMenu3->method('setAttribute')->willReturn($childMenu3);

        $childMenu4 = $this->createMock(ItemInterface::class);
        $childMenu4->method('setUri')->willReturn($childMenu4);
        $childMenu4->method('setAttribute')->willReturn($childMenu4);

        $raffleMenu = $this->createMock(ItemInterface::class);

        $parentMenuItem = $this->createMock(ItemInterface::class);
        $parentMenuItem
            ->method('addChild')
            ->with('抽奖管理')
            ->willReturn($raffleMenu)
        ;

        $raffleMenu
            ->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnOnConsecutiveCalls($childMenu1, $childMenu2, $childMenu3, $childMenu4)
        ;

        $menuProvider->__invoke($parentMenuItem);
    }

    public function testMenuProviderShouldUseCorrectControllerClasses(): void
    {
        $linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $linkGenerator
            ->expects($this->exactly(4))
            ->method('getCurdListPage')
            ->willReturnMap([
                [ActivityCrudController::class, '/mock-url'],
                [PoolCrudController::class, '/mock-url'],
                [AwardCrudController::class, '/mock-url'],
                [ChanceCrudController::class, '/mock-url'],
            ])
        ;

        self::getContainer()->set(LinkGeneratorInterface::class, $linkGenerator);
        $menuProvider = self::getService(RaffleMenuProvider::class);

        $childMenu = $this->createMock(ItemInterface::class);
        $childMenu->method('setUri')->willReturn($childMenu); // 支持链式调用
        $childMenu->method('setAttribute')->willReturn($childMenu); // 支持链式调用

        $raffleMenu = $this->createMock(ItemInterface::class);
        $raffleMenu->method('addChild')->willReturn($childMenu);

        $parentMenuItem = $this->createMock(ItemInterface::class);
        $parentMenuItem->method('addChild')->willReturn($raffleMenu);

        $menuProvider->__invoke($parentMenuItem);
    }
}
