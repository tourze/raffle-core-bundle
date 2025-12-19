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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\RaffleCoreBundle\Entity\Pool;

/**
 * 奖池管理控制器
 *
 * @extends AbstractCrudController<Pool>
 */
#[AdminCrud(
    routePath: '/raffle/pool',
    routeName: 'raffle_pool',
)]
final class PoolCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Pool::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('奖池')
            ->setEntityLabelInPlural('奖池管理')
            ->setPageTitle('index', '奖池列表')
            ->setPageTitle('new', '创建奖池')
            ->setPageTitle('edit', fn (Pool $pool) => sprintf('编辑奖池: %s', $pool->getName()))
            ->setPageTitle('detail', fn (Pool $pool) => sprintf('奖池详情: %s', $pool->getName()))
            ->setHelp('index', '管理奖池和奖品配置')
            ->setDefaultSort(['sortNumber' => 'ASC', 'id' => 'ASC'])
            ->setSearchFields(['name', 'description'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm();
        yield TextField::new('name', '奖池名称')
            ->setRequired(true)
            ->setHelp('奖池的名称')
        ;
        yield TextareaField::new('description', '奖池描述')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('奖池的详细描述')
        ;
        yield AssociationField::new('activities', '关联活动')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('使用此奖池的活动')
        ;
        yield IntegerField::new('sortNumber', '排序值')
            ->hideOnIndex()
            ->setHelp('值越小排序越靠前')
            ->setRequired(false)
        ;
        yield BooleanField::new('isDefault', '兜底奖池')
            ->renderAsSwitch(true)
            ->setHelp('如果用户没有中任何其他奖品，将会使用此兜底奖池')
        ;
        yield BooleanField::new('valid', '是否启用')
            ->renderAsSwitch(true)
        ;
        yield AssociationField::new('awards', '包含奖品')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('此奖池中的所有奖品')
        ;
        yield MoneyField::new('totalValue', '奖品总价值')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->hideOnForm()
        ;
        yield IntegerField::new('awardCount', '奖品数量')
            ->hideOnForm()
        ;
        yield DateTimeField::new('createTime', '创建时间')->hideOnForm();
        yield DateTimeField::new('updateTime', '更新时间')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('activities', '关联活动'))
            ->add(BooleanFilter::new('valid', '是否启用'))
            ->add(BooleanFilter::new('isDefault', '是否兜底'))
            ->add(NumericFilter::new('sortNumber', '排序值'))
        ;
    }
}