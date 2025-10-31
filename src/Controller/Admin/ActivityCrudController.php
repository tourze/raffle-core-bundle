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
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\RaffleCoreBundle\Entity\Activity;

/**
 * 抽奖活动管理控制器
 *
 * @extends AbstractCrudController<Activity>
 */
#[AdminCrud(
    routePath: '/raffle/activity',
    routeName: 'raffle_activity',
)]
final class ActivityCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Activity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('抽奖活动')
            ->setEntityLabelInPlural('抽奖活动列表')
            ->setPageTitle('index', '抽奖活动管理')
            ->setPageTitle('new', '创建抽奖活动')
            ->setPageTitle('edit', fn (Activity $activity) => sprintf('编辑活动: %s', $activity->getTitle()))
            ->setPageTitle('detail', fn (Activity $activity) => sprintf('活动详情: %s', $activity->getTitle()))
            ->setHelp('index', '这里列出了所有的抽奖活动')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'title', 'description'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setMaxLength(9999)
        ;

        yield from $this->getBasicInfoFields($pageName);
        yield from $this->getDisplayFields($pageName);
        yield from $this->getAssociationFields($pageName);
        yield from $this->getStatusAndAuditFields($pageName);
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getBasicInfoFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName && Crud::PAGE_DETAIL !== $pageName) {
            yield FormField::addTab('基本信息')->setIcon('fas fa-info-circle');
        }

        yield TextField::new('title', '活动标题')
            ->setRequired(true)
            ->setHelp('活动的显示名称')
        ;

        yield TextareaField::new('description', '活动描述')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('详细说明活动内容')
        ;

        yield DateTimeField::new('startTime', '开始时间')
            ->setRequired(true)
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('endTime', '结束时间')
            ->setRequired(true)
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getDisplayFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName && Crud::PAGE_DETAIL !== $pageName) {
            yield FormField::addTab('展示设置')->setIcon('fas fa-eye');
        }

        yield ImageField::new('picture', '活动图片')
            ->setBasePath('/uploads/images')
            ->setUploadDir('public/uploads/images')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setRequired(false)
            ->hideOnIndex()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getAssociationFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName && Crud::PAGE_DETAIL !== $pageName) {
            yield FormField::addTab('奖品设置')->setIcon('fas fa-gift');
        }

        // 奖品投放关联 - 暂时注释掉，因为dispatches字段不存在
        // yield AssociationField::new('dispatches', '奖品投放')
        //     ->hideOnIndex()
        //     ->setRequired(false)
        //     ->setHelp('配置活动的奖品和投放规则')
        // ;

        yield AssociationField::new('chances', '抽奖记录')
            ->hideOnIndex()
            ->hideOnForm()
            ->setHelp('用户的抽奖记录')
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getStatusAndAuditFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName && Crud::PAGE_DETAIL !== $pageName) {
            yield FormField::addTab('状态与审计')->setIcon('fas fa-toggle-on');
        }

        yield BooleanField::new('valid', '是否有效')
            ->renderAsSwitch(true)
        ;

        yield TextField::new('createdBy', '创建人')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield TextField::new('updatedBy', '更新人')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createdTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('updatedTime', '更新时间')
            ->hideOnForm()
            ->hideOnIndex()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title', '活动标题'))
            ->add(DateTimeFilter::new('startTime', '开始时间'))
            ->add(DateTimeFilter::new('endTime', '结束时间'))
            ->add(BooleanFilter::new('valid', '是否有效'))
        ;
    }
}
