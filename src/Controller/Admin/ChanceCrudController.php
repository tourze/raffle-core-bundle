<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;

/**
 * 抽奖记录管理控制器
 *
 * @extends AbstractCrudController<Chance>
 */
#[AdminCrud(
    routePath: '/raffle/chance',
    routeName: 'raffle_chance',
)]
final class ChanceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Chance::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('抽奖记录')
            ->setEntityLabelInPlural('抽奖记录列表')
            ->setPageTitle('index', '抽奖记录管理')
            ->setPageTitle('new', '创建抽奖记录')
            ->setPageTitle('edit', fn (Chance $chance) => sprintf('编辑记录: %s - %s',
                $chance->getUser()?->getUserIdentifier(),
                $chance->getActivity()?->getTitle()
            ))
            ->setPageTitle('detail', fn (Chance $chance) => sprintf('记录详情: %s - %s',
                $chance->getUser()?->getUserIdentifier(),
                $chance->getActivity()?->getTitle()
            ))
            ->setHelp('index', '查看和管理用户的抽奖记录')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'user.username', 'activity.title'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setMaxLength(9999)
        ;

        yield from $this->getBasicInfoFields($pageName);
        yield from $this->getStatusFields($pageName);
        yield from $this->getWinningFields($pageName);
        yield from $this->getAuditFields($pageName);
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getBasicInfoFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName && Crud::PAGE_DETAIL !== $pageName) {
            yield FormField::addTab('基本信息')->setIcon('fas fa-info-circle');
        }

        yield AssociationField::new('user', '用户')
            ->setRequired(true)
            ->setHelp('抽奖的用户')
        ;

        yield AssociationField::new('activity', '活动')
            ->setRequired(true)
            ->setHelp('参与的抽奖活动')
        ;

        yield DateTimeField::new('useTime', '使用时间')
            ->hideOnIndex()
            ->setRequired(false)
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('用户进行抽奖的时间')
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getStatusFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName && Crud::PAGE_DETAIL !== $pageName) {
            yield FormField::addTab('状态管理')->setIcon('fas fa-tasks');
        }

        yield ChoiceField::new('status', '状态')
            ->setChoices([
                '初始化' => ChanceStatusEnum::INIT,
                '中奖' => ChanceStatusEnum::WINNING,
                '已下单' => ChanceStatusEnum::ORDERED,
                '已发货' => ChanceStatusEnum::SHIPPED,
                '已收货' => ChanceStatusEnum::RECEIVED,
                '已过期' => ChanceStatusEnum::EXPIRED,
            ])
            ->setRequired(true)
            ->renderExpanded(false)
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getWinningFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName && Crud::PAGE_DETAIL !== $pageName) {
            yield FormField::addTab('中奖信息')->setIcon('fas fa-trophy');
        }

        yield AssociationField::new('award', '中奖奖品')
            ->hideOnIndex()
            ->setRequired(false)
            ->setHelp('如果中奖，显示中奖的奖品信息')
        ;

        // winContext 字段包含 JSON 数据，不适合在 EasyAdmin 中直接显示
      // 只在需要调试时启用
      // yield TextareaField::new('winContext', '中奖上下文')
      //     ->hideOnIndex()
      //     ->hideOnDetail()
      //     ->setRequired(false)
      //     ->setHelp('中奖时的相关信息（JSON格式）')
      // ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getAuditFields(string $pageName): iterable
    {
        yield IntegerField::new('lockVersion', '版本号')
            ->hideOnIndex()
            ->setHelp('乐观锁版本号')
        ;

        yield TextField::new('createdBy', '创建人')
            ->hideOnIndex()
        ;

        yield TextField::new('updatedBy', '更新人')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnIndex()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)
            ->disable(Action::DELETE) // 不允许删除抽奖记录
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user', '用户'))
            ->add(EntityFilter::new('activity', '活动'))
            ->add(ChoiceFilter::new('status', '状态')->setChoices([
                '初始化' => ChanceStatusEnum::INIT,
                '中奖' => ChanceStatusEnum::WINNING,
                '已下单' => ChanceStatusEnum::ORDERED,
                '已发货' => ChanceStatusEnum::SHIPPED,
                '已收货' => ChanceStatusEnum::RECEIVED,
                '已过期' => ChanceStatusEnum::EXPIRED,
            ]))
            ->add(EntityFilter::new('award', '中奖奖品'))
            ->add(DateTimeFilter::new('useTime', '使用时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
