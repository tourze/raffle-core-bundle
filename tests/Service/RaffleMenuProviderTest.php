<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
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
    private RaffleMenuProvider $menuProvider;

    private FactoryInterface $menuFactory;

    protected function onSetUp(): void
    {
        // 创建匿名类实现 LinkGeneratorInterface，避免使用 Mock
        $linkGenerator = new class implements LinkGeneratorInterface {
            public function getCurdListPage(string $entityClass): string
            {
                return match ($entityClass) {
                    ActivityCrudController::class => '/admin/activity',
                    PoolCrudController::class => '/admin/pool',
                    AwardCrudController::class => '/admin/award',
                    ChanceCrudController::class => '/admin/chance',
                    default => '/admin/' . basename(str_replace('\\', '/', $entityClass)),
                };
            }

            public function extractEntityFqcn(string $url): ?string
            {
                if (preg_match('/\/admin\/(\w+)/', $url, $matches)) {
                    return 'Tourze\\RaffleCoreBundle\\Controller\\Admin\\' . $matches[1] . 'CrudController';
                }

                return null;
            }

            public function setDashboard(string $dashboardControllerFqcn): void
            {
                // 空实现，测试中不需要实际功能
            }
        };

        $this->menuFactory = new MenuFactory();

        // 在容器中设置服务
        self::getContainer()->set(LinkGeneratorInterface::class, $linkGenerator);

        $this->menuProvider = self::getService(RaffleMenuProvider::class);
    }

    private function createMenuItem(string $name): ItemInterface
    {
        return $this->menuFactory->createItem($name);
    }

    public function testMenuProviderShouldUseCorrectControllerClasses(): void
    {
        // 创建根菜单项
        $rootMenu = $this->createMenuItem('root');

        // 执行菜单提供者
        $this->menuProvider->__invoke($rootMenu);

        // 验证菜单已正确创建
        $this->assertTrue($rootMenu->hasChildren());
        $raffleMenu = $rootMenu->getChild('抽奖管理');
        $this->assertNotNull($raffleMenu);
        $this->assertSame('#', $raffleMenu->getUri());
        $this->assertSame('fa fa-gift', $raffleMenu->getAttribute('icon'));

        // 验证子菜单项
        $expectedMenuItems = [
            '抽奖活动管理' => [
                'uri' => '/admin/activity',
                'icon' => 'fa fa-calendar-alt',
            ],
            '奖池管理' => [
                'uri' => '/admin/pool',
                'icon' => 'fa fa-swimming-pool',
            ],
            '奖品管理' => [
                'uri' => '/admin/award',
                'icon' => 'fa fa-trophy',
            ],
            '抽奖记录管理' => [
                'uri' => '/admin/chance',
                'icon' => 'fa fa-history',
            ],
        ];

        foreach ($expectedMenuItems as $menuName => $expectedData) {
            $menuItem = $raffleMenu->getChild($menuName);
            $this->assertNotNull($menuItem, "Menu item '{$menuName}' should exist");
            $this->assertSame($expectedData['uri'], $menuItem->getUri(), "URI for '{$menuName}' should be {$expectedData['uri']}");
            $this->assertSame($expectedData['icon'], $menuItem->getAttribute('icon'), "Icon for '{$menuName}' should be {$expectedData['icon']}");
        }
    }
}
