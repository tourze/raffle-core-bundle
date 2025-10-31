# 抽奖核心服务 API 使用指南

## 🎯 前台业务完整流程

### 1. 用户查看可参与的活动

```php
use Tourze\RaffleCoreBundle\Service\LotteryApiService;

// 获取用户可参与的活动列表
$availableActivities = $lotteryApiService->getAvailableActivitiesForUser($user);

// 获取具体活动详情（包括奖品列表）
$activityDetails = $lotteryApiService->getActivityDetailsForUser($activity, $user);
```

### 2. 用户参与抽奖并获得结果

```php
use Tourze\RaffleCoreBundle\Service\LotteryFlowService;

// 完整抽奖流程（参与 + 抽奖）
$result = $lotteryFlowService->executeCompleteLotteryFlow($user, $activity);

if ($result['success'] && $result['data']['won']) {
    $award = $result['data']['award'];
    $prizeInfo = $result['data']['prize_info'];
    $needConsignee = $result['data']['need_consignee'];
    
    // 如果需要收货信息，跳转到填写页面
    if ($needConsignee) {
        // 显示收货信息表单
    }
} else {
    // 显示未中奖或错误信息
    $message = $result['message'];
}
```

### 3. 用户查看个人抽奖概况

```php
// 获取用户完整概况
$overview = $lotteryFlowService->getUserLotteryOverview($user);

$activeActivities = $overview['active_activities'];      // 可参与活动
$dashboard = $overview['user_dashboard'];                // 个人统计
$pendingPrizes = $overview['pending_prizes'];            // 待领取奖品
```

### 4. 用户领取奖品（创建订单）

```php
use Tourze\RaffleCoreBundle\Service\PrizeOrderService;

// 获取待领奖品列表
$pendingPrizes = $prizeOrderService->getUserPendingPrizes($user);

// 获取奖品详情
$prizeInfo = $prizeOrderService->getPrizeOrderInfo($chance);

// 创建奖品订单
$consigneeInfo = [
    'name' => '张三',
    'phone' => '13800138000',
    'address' => '北京市朝阳区...'
];

$orderResult = $lotteryFlowService->claimPrize($chance, $consigneeInfo);

if ($orderResult['success']) {
    $orderData = $orderResult['order_data'];
    // 订单创建成功，可以集成到订单系统
}
```

## 🔧 服务层架构说明

### 核心服务职责

1. **RaffleService** - 抽奖算法引擎
   - `participateInLottery()` - 创建抽奖机会
   - `drawPrize()` - 执行抽奖逻辑
   - `canUserParticipate()` - 参与资格检查

2. **ActivityService** - 活动状态管理
   - `getActiveActivities()` - 获取进行中活动
   - `getUpcomingActivities()` - 获取即将开始活动
   - `isActivityActive()` - 单个活动状态检查
   - `getActivityStatus()` - 活动状态描述

3. **AwardService** - 奖品管理
   - `getAvailableAwardsByActivity()` - 获取活动奖品
   - `getEligibleAwardsForLottery()` - 获取可抽奖品
   - `isAwardEligible()` - 奖品有效性检查
   - `decreaseAwardStock()` - 原子扣减库存

4. **ChanceService** - 用户记录管理
   - `getUserChancesByActivity()` - 用户活动参与记录
   - `getUserWinningHistory()` - 用户中奖历史
   - `getUserChanceCount()` - 用户参与次数统计
   - `markChanceAsOrdered()` - 标记奖品已下单

5. **PrizeOrderService** - 奖品订单管理
   - `getUserPendingPrizes()` - 用户待领奖品
   - `getPrizeOrderInfo()` - 奖品详情信息
   - `createOrderFromPrize()` - 从中奖创建订单
   - `validatePrizeClaimable()` - 验证奖品可领取性

6. **LotteryApiService** - 前台API聚合
   - `getAvailableActivitiesForUser()` - 用户可参与活动
   - `getActivityDetailsForUser()` - 活动详情聚合
   - `participateAndDraw()` - 参与抽奖聚合
   - `getUserLotteryDashboard()` - 用户抽奖面板

7. **LotteryFlowService** - 完整业务流程
   - `executeCompleteLotteryFlow()` - 完整抽奖流程
   - `claimPrize()` - 奖品领取流程
   - `getUserLotteryOverview()` - 用户概况聚合

## 🎲 典型集成场景

### Controller 层集成

```php
// 抽奖页面 API
#[Route('/api/lottery/participate/{activityId}', methods: ['POST'])]
public function participate(int $activityId, LotteryFlowService $lotteryFlow): JsonResponse
{
    $activity = $activityRepository->find($activityId);
    $user = $this->getUser();
    
    $result = $lotteryFlow->executeCompleteLotteryFlow($user, $activity);
    
    return $this->json($result);
}

// 用户中心页面
#[Route('/api/user/lottery/overview', methods: ['GET'])]
public function overview(LotteryFlowService $lotteryFlow): JsonResponse
{
    $user = $this->getUser();
    $overview = $lotteryFlow->getUserLotteryOverview($user);
    
    return $this->json($overview);
}

// 奖品领取 API
#[Route('/api/lottery/claim/{chanceId}', methods: ['POST'])]
public function claimPrize(int $chanceId, Request $request, LotteryFlowService $lotteryFlow): JsonResponse
{
    $chance = $chanceRepository->find($chanceId);
    $consigneeInfo = $request->request->all();
    
    $result = $lotteryFlow->claimPrize($chance, $consigneeInfo);
    
    return $this->json($result);
}
```

### 移动端/小程序集成

```php
// 活动列表页
$activities = $lotteryApiService->getAvailableActivitiesForUser($user);

// 活动详情页
$details = $lotteryApiService->getActivityDetailsForUser($activity, $user);

// 抽奖执行
$result = $lotteryApiService->participateAndDraw($user, $activity);

// 个人中心
$dashboard = $lotteryApiService->getUserLotteryDashboard($user);
```

## 🚀 性能优化建议

1. **批量查询**: 使用 Repository 的批量方法，避免 N+1 问题
2. **缓存策略**: 活动和奖品信息适合缓存
3. **事务控制**: 抽奖流程使用事务保证数据一致性
4. **原子操作**: 库存扣减使用原子更新防止超卖

## 🛡️ 安全注意事项

1. **用户验证**: 所有 API 都需要用户认证
2. **活动权限**: 检查用户是否有权参与特定活动  
3. **重复参与**: 通过业务规则控制用户参与频次
4. **数据完整性**: 中奖记录和库存操作要保证一致性