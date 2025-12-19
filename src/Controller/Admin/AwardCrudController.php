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
use Tourze\RaffleCoreBundle\Entity\Award;

/**
 * 奖品管理控制器
 *
 * @extends AbstractCrudController<Award>
 */
#[AdminCrud(
    routePath: '/raffle/award',
    routeName: 'raffle_award',
)]
final class AwardCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Award::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('奖品')
            ->setEntityLabelInPlural('奖品管理')
            ->setPageTitle('index', '奖品列表')
            ->setPageTitle('new', '创建奖品')
            ->setPageTitle('edit', '编辑奖品')
            ->setPageTitle('detail', '奖品详情')
            ->setDefaultSort(['sortNumber' => 'ASC', 'id' => 'ASC'])
        ;
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

    /**
     * @return FieldInterface[]
     */
    public function configureFields(string $pageName): iterable
    {
        // ID 字段
        yield IdField::new('id', 'ID')->hideOnForm();
        yield TextField::new('name', '奖品名称')->setMaxLength(255);
        yield TextareaField::new('description', '奖品描述')->hideOnIndex();

        yield AssociationField::new('pool', '所属奖池')
            ->setRequired(true)
            ->autocomplete()
        ;
        yield AssociationField::new('sku', 'SKU')
            ->setRequired(true)
            ->autocomplete()
        ;
        yield IntegerField::new('probability', '概率权重')
            ->setHelp('数值越大，中奖概率越高')
        ;
        yield IntegerField::new('quantity', '库存数量')
            ->setHelp('总共可派发的数量')
        ;
        yield IntegerField::new('dayLimit', '每日限制')
            ->setHelp('每日最多派发数量，不填则不限制')
        ;
        yield IntegerField::new('amount', '单次数量')
            ->setHelp('单次中奖派发的数量')
        ;
        yield MoneyField::new('value', '奖品价值')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
        ;
        yield IntegerField::new('sortNumber', '排序号')
            ->setHelp('数值越小，排序越靠前')
        ;
        yield BooleanField::new('needConsignee', '需要收货地址');
        yield BooleanField::new('valid', '启用状态');

        if (Crud::PAGE_INDEX === $pageName) {
            yield DateTimeField::new('createTime', '创建时间')->hideOnForm();
            yield DateTimeField::new('updateTime', '更新时间')->hideOnForm();
        }
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('pool', '奖池'))
            ->add(EntityFilter::new('sku', 'SKU'))
            ->add(BooleanFilter::new('valid', '启用状态'))
            ->add(BooleanFilter::new('needConsignee', '需要收货地址'))
            ->add(NumericFilter::new('probability', '概率权重'))
            ->add(NumericFilter::new('quantity', '库存数量'))
        ;
    }
}
