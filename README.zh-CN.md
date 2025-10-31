# 抽奖核心包 (Raffle Core Bundle)

[English](README.md) | [中文](README.zh-CN.md)

一个功能完整的 Symfony 抽奖系统包，支持活动管理、奖池配置、奖品设置和抽奖记录跟踪。

## 特性

- 🎯 **活动管理**：创建和管理抽奖活动，支持灵活的时间控制
- 🏊 **奖池系统**：将奖品组织到逻辑奖池中，便于管理
- 🏆 **奖品配置**：详细的奖品设置，包括概率、数量和约束条件
- 🎲 **机会跟踪**：完整的抽奖参与和中奖记录管理
- 🔧 **EasyAdmin集成**：为所有实体提供开箱即用的管理界面
- 📊 **全面测试**：PHPUnit 完整测试覆盖，PHPStan Level 8 静态分析

## 架构设计

### 核心实体

```
Activity (抽奖活动)
    ↕️ 多对多关系
Pool (奖池)
    ↕️ 一对多关系  
Award (奖品)
    ↕️ 多对一关系
SKU (商品)

User → Chance (抽奖机会) → Award (中奖奖品)
```

### 实体关系

- **Activity ↔ Pool**：多对多关系，活动可以共享奖池
- **Pool → Award**：一对多关系，奖池包含多个奖品
- **Award → SKU**：多对一关系，奖品关联到商品SKU
- **Chance → Award**：多对一关系，记录中奖情况

## 安装

```bash
composer require tourze/raffle-core-bundle
```

在 `config/bundles.php` 中添加包：

```php
return [
    // ...
    Tourze\RaffleCoreBundle\RaffleCoreBundle::class => ['all' => true],
];
```

运行数据库迁移：

```bash
php bin/console doctrine:migrations:migrate
```

## 配置

### 基础配置

```yaml
# config/packages/raffle_core.yaml
raffle_core:
    default_activity_duration: '7 days'    # 默认活动持续时间
    max_chances_per_user: 10               # 每个用户最大抽奖次数
    enable_chance_expiry: true             # 启用抽奖机会过期
    expiry_duration: '24 hours'            # 过期时间
```

### EasyAdmin 集成

```php
// src/Controller/Admin/DashboardController.php
use Tourze\RaffleCoreBundle\Controller\Admin\ActivityCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\PoolCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\AwardCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\ChanceCrudController;

public function configureMenuItems(): iterable
{
    yield MenuItem::linkToCrud('抽奖活动', 'fa fa-calendar', Activity::class);
    yield MenuItem::linkToCrud('奖池管理', 'fa fa-swimming-pool', Pool::class);
    yield MenuItem::linkToCrud('奖品管理', 'fa fa-trophy', Award::class);
    yield MenuItem::linkToCrud('抽奖记录', 'fa fa-history', Chance::class);
}
```

## 使用方法

### 创建抽奖活动

```php
use Tourze\RaffleCoreBundle\Entity\Activity;
use Carbon\CarbonImmutable;

$activity = new Activity();
$activity->setTitle('2024夏季大抽奖')
    ->setDescription('赢取精美奖品！')
    ->setStartTime(CarbonImmutable::now())
    ->setEndTime(CarbonImmutable::now()->addWeek())
    ->setValid(true);

$entityManager->persist($activity);
```

### 设置奖池和奖品

```php
use Tourze\RaffleCoreBundle\Entity\Pool;
use Tourze\RaffleCoreBundle\Entity\Award;

// 创建奖池
$pool = new Pool();
$pool->setName('主要奖品奖池')
    ->setDescription('包含高价值奖品')
    ->setValid(true)
    ->setSortNumber(1);

$pool->addActivity($activity);

// 创建奖品
$award = new Award();
$award->setName('iPhone 15 Pro Max')
    ->setDescription('苹果最新智能手机')
    ->setPool($pool)
    ->setSku($sku)
    ->setProbability(10)      // 数值越小概率越低
    ->setQuantity(5)          // 总共可派发数量
    ->setDayLimit(2)          // 每日最多派发2个
    ->setAmount(1)            // 每次中奖数量
    ->setValue('1999.00')     // 奖品价值
    ->setNeedConsignee(true)  // 需要收货地址
    ->setValid(true)
    ->setSortNumber(1);

$entityManager->persist($pool);
$entityManager->persist($award);
```

### 记录抽奖机会

```php
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;

// 为用户创建抽奖机会
$chance = new Chance();
$chance->setActivity($activity)
    ->setUser($user)
    ->setStatus(ChanceStatusEnum::INIT);

// 标记为中奖
$chance->markAsWinning($award, [
    'prize_name' => 'iPhone 15 Pro Max',
    'win_time' => CarbonImmutable::now()->toDateTimeString(),
]);
```

### Repository 使用

```php
// 获取活动的可用奖品
$awards = $awardRepository->findAvailableByActivity($activity);

// 查找符合条件的奖品（考虑每日限制）
$eligibleAwards = $awardRepository->findEligibleForLotteryByActivity($activity);

// 原子性地减少奖品数量
$success = $awardRepository->decreaseQuantityAtomically($award, 1);

// 检查今天是否还能派发此奖品
$canDispatch = $awardRepository->canDispatchToday($award);
```

## 测试

运行测试套件：

```bash
# 运行所有测试
./vendor/bin/phpunit packages/raffle-core-bundle/tests

# 运行特定类型的测试
./vendor/bin/phpunit packages/raffle-core-bundle/tests/Entity
./vendor/bin/phpunit packages/raffle-core-bundle/tests/Repository
./vendor/bin/phpunit packages/raffle-core-bundle/tests/Controller

# 运行 PHPStan 静态分析
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/raffle-core-bundle/src --level=8
```

## 数据库结构

### 主要数据表

- `raffle_activity` - 抽奖活动表
- `raffle_pool` - 奖池表，用于组织奖品
- `raffle_award` - 奖品表，包含详细配置
- `raffle_chance` - 用户参与和中奖记录表
- `raffle_activity_pool` - 活动与奖池的多对多关联表

### 索引优化

为常见查询优化的索引：
- 活动状态和时间查询
- 奖池和奖品过滤
- 抽奖状态和时间分析
- 用户参与情况跟踪

## API 参考

### 枚举类

#### ChanceStatusEnum (抽奖状态)
- `INIT` - 初始状态，尚未使用
- `WINNING` - 用户已中奖
- `ORDERED` - 奖品已下单/领取
- `EXPIRED` - 机会已过期

### 事件

包会为关键操作分发事件：
- `ChanceCreatedEvent` - 创建新抽奖机会时
- `AwardWonEvent` - 用户中奖时
- `AwardOrderedEvent` - 奖品被下单时

## 高级特性

### 自定义概率表达式

奖品支持动态概率计算：

```php
$award->setProbabilityExpression('base_probability * user_level_modifier');
```

### 批量操作

高效处理批量操作：

```php
// 批量为活动创建抽奖机会
$chances = $chanceRepository->createBulkChances($activity, $users);

// 批量更新奖品数量
$awardRepository->updateQuantities($updates);
```

### 分析支持

内置常用分析方法：

```php
// 每日派发统计
$stats = $awardRepository->getDailyDispatchStats($award, $date);

// 活动参与率
$rates = $activityRepository->getParticipationRates($activity);
```

## 性能考虑

- 使用原子操作进行数量递减，防止竞态条件
- 为基于时间的查询实现适当的索引
- 考虑对频繁访问的奖品配置进行缓存
- 使用批量操作进行批处理

## 业务场景示例

### 1. 简单抽奖活动

```php
// 创建"每日签到抽奖"活动
$activity = new Activity();
$activity->setTitle('每日签到抽奖')
    ->setStartTime(CarbonImmutable::today())
    ->setEndTime(CarbonImmutable::today()->addMonth())
    ->setValid(true);

// 创建兜底奖池（保证用户总能中点什么）
$consolationPool = new Pool();
$consolationPool->setName('兜底奖池')
    ->setIsDefault(true)  // 标记为兜底奖池
    ->setValid(true);

// 添加积分奖励作为兜底奖品
$pointsAward = new Award();
$pointsAward->setName('10积分')
    ->setPool($consolationPool)
    ->setProbability(10000)  // 很高的概率
    ->setQuantity(999999)    // 充足的数量
    ->setValue('0.10')
    ->setValid(true);
```

### 2. 等级抽奖系统

```php
// VIP用户专享奖池
$vipPool = new Pool();
$vipPool->setName('VIP专享奖池')
    ->setDescription('仅VIP用户可参与')
    ->setSortNumber(1);

// 高价值奖品，低概率
$luxuryAward = new Award();
$luxuryAward->setName('MacBook Pro')
    ->setPool($vipPool)
    ->setProbability(1)      // 超低概率
    ->setQuantity(1)         // 限量1台
    ->setDayLimit(1)         // 每天最多1台
    ->setValue('15999.00')
    ->setNeedConsignee(true)
    ->setValid(true);
```

## 故障排除

### 常见问题

1. **奖品数量不足**
   ```php
   // 检查奖品剩余数量
   if ($award->getQuantity() <= 0) {
       throw new InsufficientAwardException('奖品库存不足');
   }
   ```

2. **达到每日限制**
   ```php
   // 检查今日派发限制
   if (!$awardRepository->canDispatchToday($award)) {
       throw new DailyLimitExceededException('今日派发数量已达上限');
   }
   ```

3. **活动时间检查**
   ```php
   // 验证活动是否在有效期内
   if (!$activity->isActive()) {
       throw new ActivityInactiveException('活动未开始或已结束');
   }
   ```

## 最佳实践

1. **奖池设计**：按奖品价值或类型分组，便于管理
2. **概率配置**：使用相对权重而非绝对概率，更灵活
3. **库存管理**：定期监控奖品库存，及时补充
4. **日志记录**：记录所有抽奖操作，便于审计
5. **并发控制**：使用事务和锁机制防止超发

## 贡献指南

1. Fork 项目仓库
2. 创建功能分支
3. 为新功能编写测试
4. 确保通过 PHPStan Level 8 检查
5. 提交 Pull Request

## 许可证

本包基于 MIT 许可证发布。

## 技术支持

如有问题或功能请求，请使用项目的 Issue 跟踪器。