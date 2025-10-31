# Raffle Core Bundle 系统设计文档（v2.0 - Pool+Award架构）

## 📋 架构演进概述

### 设计演进历程
1. **v1.0 设计**: Activity ↔ DispatchLog ↔ SKU 的直接映射关系
2. **v2.0 重构**: 引入 Pool（奖池）概念，实现更灵活的奖品管理架构
3. **业务优化**: DispatchLog → Pool + Award 的职责分离设计

### 核心改进
- **概念清晰化**: 奖池（Pool）和奖品（Award）的业务概念分离
- **复用性增强**: 多个活动可以共享同一奖池
- **管理便利性**: 奖品按奖池组织，层次更清晰
- **扩展性提升**: 支持更复杂的奖品配置和管理策略

## 🎯 新架构实体设计

### Activity 实体（抽奖活动）

```php
class Activity 
{
    // 基础信息
    private ?int $id = null;
    private string $title;                    // 活动标题
    private ?string $description = null;      // 活动描述
    private ?string $picture = null;          // 活动图片
    
    // 时间控制
    private DateTimeImmutable $startTime;     // 开始时间
    private DateTimeImmutable $endTime;       // 结束时间
    
    // 关联关系 - 多对多
    private Collection $pools;                // 多对多：关联奖池
    private Collection $chances;              // 一对多：抽奖机会
    
    // 状态控制
    private bool $valid = true;               // 是否有效
    
    use TimestampableAware;
    use BlameableAware;
}
```

### Pool 实体（奖池）- 新增

```php
class Pool
{
    private ?int $id = null;
    private string $name = '';                // 奖池名称
    private ?string $description = null;      // 奖池描述
    
    // 配置属性
    private bool $isDefault = false;          // 是否兜底奖池
    private bool $valid = true;               // 是否启用
    private int $sortNumber = 0;              // 排序号
    
    // 关联关系
    private Collection $activities;           // 多对多：关联活动
    private Collection $awards;               // 一对多：包含奖品
    
    use TimestampableAware;
    use BlameableAware;
}
```

#### Pool 设计要点
- **逻辑分组**: 将相关奖品组织到同一奖池
- **复用机制**: 多个活动可共享同一奖池
- **兜底策略**: 支持设置兜底奖池确保用户总能中奖
- **灵活管理**: 独立的启用/禁用控制

### Award 实体（奖品）- 重构核心

```php
class Award
{
    private ?int $id = null;
    private string $name = '';                // 奖品名称
    private ?string $description = null;      // 奖品描述
    
    // 关联关系
    private ?Pool $pool = null;               // 多对一：所属奖池
    private ?SKU $sku = null;                 // 多对一：关联商品
    
    // 概率配置
    private int $probability = 0;             // 中奖权重
    
    // 数量管理
    private int $quantity = 0;                // 总投放数量
    private ?int $dayLimit = null;           // 每日限制
    private int $amount = 1;                 // 单次派发数量
    
    // 奖品属性
    private string $value = '0.00';          // 奖品价值
    private bool $needConsignee = false;      // 是否需要收货地址
    
    // 状态控制
    private bool $valid = true;               // 是否启用
    private int $sortNumber = 0;              // 排序号
    
    // 关联记录
    private Collection $chances;              // 一对多：中奖记录
    
    use TimestampableAware;
    use BlameableAware;
}
```

#### Award 设计要点
- **独立配置**: 每个奖品有独立的概率、数量、限制配置
- **SKU关联**: 复用 product-core-bundle 的商品体系
- **精细控制**: 支持每日限制、单次数量等精细化配置
- **状态管理**: 独立的启用状态和排序控制

### Chance 实体（抽奖机会）- 关联调整

```php
class Chance
{
    private ?int $id = null;
    private Activity $activity;               // 关联活动
    private BizUser $user;                   // 关联用户
    
    // 状态管理
    private ChanceStatusEnum $status = ChanceStatusEnum::INIT;
    private ?DateTimeImmutable $useTime = null;
    
    // 中奖信息 - 调整为Award关联
    private ?Award $award = null;            // 关联中奖奖品
    private ?array $winContext = null;       // 中奖上下文
    
    // 并发控制
    private ?int $lockVersion = null;
    
    use TimestampableAware;
    use BlameableAware;
}
```

#### Chance 关联调整
- **直接关联**: 从 `DispatchLog` 调整为直接关联 `Award`
- **语义明确**: 中奖记录直接指向具体奖品，更符合业务逻辑
- **查询优化**: 减少关联查询层次，提高查询性能

## 🔄 架构关系图

### 新架构实体关系

```
Activity (抽奖活动)
    ↕️ Many-to-Many (activity_pool 关联表)
Pool (奖池)
    ↕️ One-to-Many  
Award (奖品)
    ↕️ Many-to-One
SKU (商品)

BizUser (用户) → Chance (抽奖机会) → Award (中奖奖品)
```

### 具体关系说明

1. **Activity ↔ Pool**: 多对多关系
   - 一个活动可以使用多个奖池
   - 一个奖池可以被多个活动使用
   - 通过关联表 `raffle_activity_pool` 维护关系

2. **Pool → Award**: 一对多关系
   - 一个奖池包含多个奖品
   - 每个奖品只属于一个奖池
   - 奖品按奖池进行组织管理

3. **Award → SKU**: 多对一关系
   - 每个奖品关联一个商品SKU
   - 复用商品系统的完整信息

4. **Chance → Award**: 多对一关系
   - 中奖记录直接关联到具体奖品
   - 便于统计和查询

## 🚀 业务流程优化

### 抽奖流程（优化版）

1. **用户发起抽奖**
   - 创建 `Chance(status=INIT, activity, user)`
   - 验证活动有效性和用户参与资格

2. **奖池和奖品筛选**
   - 查询活动关联的所有有效奖池
   - 获取奖池中的所有可用奖品
   - 应用每日限制和库存限制

3. **概率算法计算**
   - 基于奖品的 `probability` 权重计算
   - 支持兜底奖池的保底机制
   - 确保用户总能获得奖品

4. **中奖处理**
   - 原子性扣减 `Award.quantity`
   - 更新 `Chance.status = WINNING`
   - 记录中奖奖品和上下文信息

5. **后续处理**
   - 创建 OfferChance 对接订单系统
   - 发送中奖通知
   - 处理收货地址等后续流程

### 管理流程优化

1. **奖池管理**
   - 按业务逻辑创建不同类型奖池
   - 设置兜底奖池确保用户体验
   - 灵活配置奖池与活动的关联

2. **奖品配置**
   - 在奖池内添加具体奖品
   - 设置每个奖品的详细参数
   - 支持实时调整概率和数量

3. **活动设置**
   - 选择需要的奖池组合
   - 配置活动时间和规则
   - 启动活动开始抽奖

## 📊 数据库设计

### 核心数据表

```sql
-- 活动表
CREATE TABLE raffle_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    picture VARCHAR(500),
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    valid BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 奖池表（新增）
CREATE TABLE raffle_pool (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    valid BOOLEAN DEFAULT TRUE,
    sort_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 活动奖池关联表（多对多）
CREATE TABLE raffle_activity_pool (
    activity_id INT NOT NULL,
    pool_id INT NOT NULL,
    PRIMARY KEY (activity_id, pool_id),
    FOREIGN KEY (activity_id) REFERENCES raffle_activity(id) ON DELETE CASCADE,
    FOREIGN KEY (pool_id) REFERENCES raffle_pool(id) ON DELETE CASCADE
);

-- 奖品表（重构）
CREATE TABLE raffle_award (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pool_id INT NOT NULL,
    sku_id INT NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    probability INT DEFAULT 0,
    quantity INT DEFAULT 0,
    day_limit INT,
    amount INT DEFAULT 1,
    value DECIMAL(10,2) DEFAULT 0.00,
    need_consignee BOOLEAN DEFAULT FALSE,
    valid BOOLEAN DEFAULT TRUE,
    sort_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pool_id) REFERENCES raffle_pool(id) ON DELETE CASCADE,
    FOREIGN KEY (sku_id) REFERENCES product_sku(id)
);

-- 抽奖机会表（关联调整）
CREATE TABLE raffle_chance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    user_id INT NOT NULL,
    award_id INT,
    status ENUM('init', 'winning', 'ordered', 'expired') DEFAULT 'init',
    use_time TIMESTAMP NULL,
    win_context JSON,
    lock_version INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES raffle_activity(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES biz_user(id),
    FOREIGN KEY (award_id) REFERENCES raffle_award(id)
);
```

### 索引优化

```sql
-- 活动查询优化
CREATE INDEX idx_activity_time_valid ON raffle_activity(start_time, end_time, valid);

-- 奖池查询优化  
CREATE INDEX idx_pool_valid_sort ON raffle_pool(valid, sort_number);

-- 奖品查询优化
CREATE INDEX idx_award_pool_valid ON raffle_award(pool_id, valid);
CREATE INDEX idx_award_sort ON raffle_award(sort_number);

-- 抽奖机会查询优化
CREATE INDEX idx_chance_activity_user_status ON raffle_chance(activity_id, user_id, status);
CREATE INDEX idx_chance_award ON raffle_chance(award_id);
CREATE INDEX idx_chance_use_time ON raffle_chance(use_time);
```

## 🔧 Repository 层设计

### AwardRepository 核心方法

```php
class AwardRepository extends ServiceEntityRepository
{
    // 获取活动的所有可用奖品
    public function findAvailableByActivity(Activity $activity): array
    
    // 获取奖池的可用奖品
    public function findAvailableByPool(Pool $pool): array
    
    // 获取符合抽奖条件的奖品（考虑每日限制）
    public function findEligibleForLotteryByActivity(Activity $activity): array
    
    // 原子性减少奖品数量
    public function decreaseQuantityAtomically(Award $award, int $amount): bool
    
    // 检查今天是否还能派发此奖品
    public function canDispatchToday(Award $award): bool
    
    // 统计今日已派发数量
    public function countTodayDispatchedByAward(Award $award): int
}
```

### PoolRepository 核心方法

```php
class PoolRepository extends ServiceEntityRepository
{
    // 获取所有有效奖池
    public function findValidPools(): array
    
    // 获取活动关联的奖池
    public function findByActivity(Activity $activity): array
    
    // 获取兜底奖池
    public function findDefaultPools(): array
}
```

## ✅ 架构优势分析

### 业务优势

1. **概念清晰**: 奖池和奖品的分离更符合业务直觉
2. **管理便利**: 奖品按奖池组织，管理层次更清晰
3. **复用性强**: 奖池可以被多个活动复用，减少重复配置
4. **扩展灵活**: 支持更复杂的奖品分类和管理策略

### 技术优势

1. **职责单一**: 每个实体职责更加明确
2. **查询优化**: 减少复杂的关联查询
3. **并发安全**: 保持原有的并发控制机制
4. **测试友好**: 实体关系更清晰，测试更容易编写

### 维护优势

1. **代码清晰**: 实体关系更直观，代码更易理解
2. **修改隔离**: 奖池和奖品的修改影响范围更小
3. **扩展容易**: 新增奖品类型或管理功能更容易
4. **调试方便**: 问题排查时层次更清晰

## 🔄 迁移策略

### 数据迁移方案

1. **DispatchLog → Pool + Award 拆分**
   - 为每个 DispatchLog 创建对应的 Pool 和 Award
   - 迁移所有配置字段到 Award
   - 建立新的关联关系

2. **Chance 关联更新**
   - 将 Chance.dispatchLog 关联更新为 Award 关联
   - 保持所有历史数据的完整性

3. **渐进式迁移**
   - 支持新旧架构并存的过渡期
   - 确保迁移过程中系统正常运行

### 代码迁移

1. **Controller 更新**
   - DispatchLogCrudController → PoolCrudController + AwardCrudController
   - 更新管理界面和菜单配置

2. **Service 层适配**
   - 更新抽奖算法以使用新的实体关系
   - 调整概率计算和奖品选择逻辑

3. **测试更新**
   - 更新所有相关测试用例
   - 确保新架构的测试覆盖率

## 📈 性能影响评估

### 查询性能

- **改进**: 减少了复杂的三表关联查询
- **优化**: 通过合理索引提升查询效率
- **缓存**: 奖池配置更适合缓存策略

### 存储优化

- **规范化**: 减少数据冗余，提高存储效率
- **扩展性**: 新架构更适合大规模数据场景
- **维护性**: 数据一致性更容易保证

### 并发处理

- **保持优势**: 保留原有的并发安全机制
- **性能提升**: 减少锁的粒度和范围
- **扩展支持**: 支持更复杂的并发场景

---

**架构演进理念**: 基于业务需求的真实变化，通过合理的实体重构和关系设计，实现更清晰、更灵活、更易维护的抽奖系统架构。在保持系统稳定性的前提下，提升业务表达能力和扩展性。