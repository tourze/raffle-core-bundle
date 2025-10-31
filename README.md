# Raffle Core Bundle

[English](README.md) | [中文](README.zh-CN.md)

A comprehensive Symfony bundle for managing lottery/raffle systems with support for activities, prize pools, awards, and chances.

## Features

- 🎯 **Activity Management**: Create and manage raffle activities with flexible time controls
- 🏊 **Prize Pool System**: Organize awards into logical pools for better management
- 🏆 **Award Configuration**: Detailed prize setup with probability, quantity, and constraints
- 🎲 **Chance Tracking**: Complete lottery participation and winning record management
- 🔧 **EasyAdmin Integration**: Ready-to-use admin interfaces for all entities
- 📊 **Comprehensive Testing**: Full test coverage with PHPUnit and PHPStan Level 8

## Architecture

### Core Entities

```
Activity (抽奖活动)
    ↕️ Many-to-Many
Pool (奖池)
    ↕️ One-to-Many  
Award (奖品)
    ↕️ Many-to-One
SKU (商品)

User → Chance (抽奖机会) → Award (中奖奖品)
```

### Entity Relationships

- **Activity ↔ Pool**: Many-to-many relationship allowing activities to share prize pools
- **Pool → Award**: One-to-many relationship organizing awards within pools  
- **Award → SKU**: Many-to-one relationship linking awards to products
- **Chance → Award**: Many-to-one relationship tracking winning records

## Installation

```bash
composer require tourze/raffle-core-bundle
```

Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Tourze\RaffleCoreBundle\RaffleCoreBundle::class => ['all' => true],
];
```

Run the database migrations:

```bash
php bin/console doctrine:migrations:migrate
```

## Configuration

### Basic Setup

```yaml
# config/packages/raffle_core.yaml
raffle_core:
    default_activity_duration: '7 days'
    max_chances_per_user: 10
    enable_chance_expiry: true
    expiry_duration: '24 hours'
```

### EasyAdmin Integration

```php
// src/Controller/Admin/DashboardController.php
use Tourze\RaffleCoreBundle\Controller\Admin\ActivityCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\PoolCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\AwardCrudController;
use Tourze\RaffleCoreBundle\Controller\Admin\ChanceCrudController;

public function configureMenuItems(): iterable
{
    yield MenuItem::linkToCrud('Activities', 'fa fa-calendar', Activity::class);
    yield MenuItem::linkToCrud('Prize Pools', 'fa fa-swimming-pool', Pool::class);
    yield MenuItem::linkToCrud('Awards', 'fa fa-trophy', Award::class);
    yield MenuItem::linkToCrud('Chances', 'fa fa-history', Chance::class);
}
```

## Usage

### Creating a Raffle Activity

```php
use Tourze\RaffleCoreBundle\Entity\Activity;
use Carbon\CarbonImmutable;

$activity = new Activity();
$activity->setTitle('Summer Lottery 2024')
    ->setDescription('Win amazing prizes!')
    ->setStartTime(CarbonImmutable::now())
    ->setEndTime(CarbonImmutable::now()->addWeek())
    ->setValid(true);

$entityManager->persist($activity);
```

### Setting up Prize Pools and Awards

```php
use Tourze\RaffleCoreBundle\Entity\Pool;
use Tourze\RaffleCoreBundle\Entity\Award;

// Create prize pool
$pool = new Pool();
$pool->setName('Main Prizes')
    ->setDescription('High-value prizes')
    ->setValid(true)
    ->setSortNumber(1);

$pool->addActivity($activity);

// Create award
$award = new Award();
$award->setName('iPhone 15 Pro Max')
    ->setDescription('Latest Apple smartphone')
    ->setPool($pool)
    ->setSku($sku)
    ->setProbability(10)  // Lower number = lower probability
    ->setQuantity(5)      // Total available
    ->setDayLimit(2)      // Max 2 per day
    ->setAmount(1)        // Quantity per win
    ->setValue('1999.00')
    ->setNeedConsignee(true)
    ->setValid(true)
    ->setSortNumber(1);

$entityManager->persist($pool);
$entityManager->persist($award);
```

### Recording Lottery Chances

```php
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;

// Create a chance for user
$chance = new Chance();
$chance->setActivity($activity)
    ->setUser($user)
    ->setStatus(ChanceStatusEnum::INIT);

// Mark as winning
$chance->markAsWinning($award, [
    'prize_name' => 'iPhone 15 Pro Max',
    'win_time' => CarbonImmutable::now()->toDateTimeString(),
]);
```

### Repository Usage

```php
// Get available awards for an activity
$awards = $awardRepository->findAvailableByActivity($activity);

// Find eligible awards (respecting daily limits)
$eligibleAwards = $awardRepository->findEligibleForLotteryByActivity($activity);

// Atomically decrease award quantity
$success = $awardRepository->decreaseQuantityAtomically($award, 1);

// Check if award can be dispatched today
$canDispatch = $awardRepository->canDispatchToday($award);
```

## Testing

Run the test suite:

```bash
# Run all tests
./vendor/bin/phpunit packages/raffle-core-bundle/tests

# Run specific test types
./vendor/bin/phpunit packages/raffle-core-bundle/tests/Entity
./vendor/bin/phpunit packages/raffle-core-bundle/tests/Repository
./vendor/bin/phpunit packages/raffle-core-bundle/tests/Controller

# Run PHPStan analysis
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/raffle-core-bundle/src --level=8
```

## Database Schema

### Key Tables

- `raffle_activity` - Raffle activities/campaigns
- `raffle_pool` - Prize pools for organizing awards
- `raffle_award` - Individual prizes with configuration
- `raffle_chance` - User participation and winning records
- `raffle_activity_pool` - Many-to-many relationship table

### Indexes

Optimized indexes for common queries:
- Activity status and time-based queries
- Pool and award filtering
- Chance status and time-based analytics
- User participation tracking

## API Reference

### Enums

#### ChanceStatusEnum
- `INIT` - Initial state, not yet used
- `WINNING` - User has won an award
- `ORDERED` - Prize has been ordered/claimed
- `EXPIRED` - Chance has expired

### Events

The bundle dispatches events for key actions:
- `ChanceCreatedEvent` - When a new chance is created
- `AwardWonEvent` - When a user wins an award
- `AwardOrderedEvent` - When a prize is ordered

## Advanced Features

### Custom Probability Expressions

Awards support dynamic probability calculations:

```php
$award->setProbabilityExpression('base_probability * user_level_modifier');
```

### Batch Operations

Efficiently handle bulk operations:

```php
// Bulk create chances for activity
$chances = $chanceRepository->createBulkChances($activity, $users);

// Batch update award quantities
$awardRepository->updateQuantities($updates);
```

### Analytics Support

Built-in methods for common analytics:

```php
// Daily dispatch statistics
$stats = $awardRepository->getDailyDispatchStats($award, $date);

// Activity participation rates
$rates = $activityRepository->getParticipationRates($activity);
```

## Performance Considerations

- Use atomic operations for quantity decrements to prevent race conditions
- Implement proper indexing for time-based queries
- Consider caching for frequently accessed award configurations
- Use bulk operations for batch processing

## Business Scenarios

### 1. Simple Daily Check-in Lottery

```php
// Create daily check-in lottery
$activity = new Activity();
$activity->setTitle('Daily Check-in Lottery')
    ->setStartTime(CarbonImmutable::today())
    ->setEndTime(CarbonImmutable::today()->addMonth())
    ->setValid(true);

// Create fallback pool (ensure users always win something)
$consolationPool = new Pool();
$consolationPool->setName('Consolation Pool')
    ->setIsDefault(true)  // Mark as fallback pool
    ->setValid(true);

// Add points as consolation prize
$pointsAward = new Award();
$pointsAward->setName('10 Points')
    ->setPool($consolationPool)
    ->setProbability(10000)  // High probability
    ->setQuantity(999999)    // Abundant quantity
    ->setValue('0.10')
    ->setValid(true);
```

### 2. VIP Tier Lottery System

```php
// VIP exclusive prize pool
$vipPool = new Pool();
$vipPool->setName('VIP Exclusive Pool')
    ->setDescription('VIP users only')
    ->setSortNumber(1);

// High-value prize with low probability
$luxuryAward = new Award();
$luxuryAward->setName('MacBook Pro')
    ->setPool($vipPool)
    ->setProbability(1)      // Ultra-low probability
    ->setQuantity(1)         // Limited to 1 unit
    ->setDayLimit(1)         // Max 1 per day
    ->setValue('15999.00')
    ->setNeedConsignee(true)
    ->setValid(true);
```

## Troubleshooting

### Common Issues

1. **Insufficient Award Quantity**
   ```php
   // Check remaining award quantity
   if ($award->getQuantity() <= 0) {
       throw new InsufficientAwardException('Award out of stock');
   }
   ```

2. **Daily Limit Reached**
   ```php
   // Check daily dispatch limit
   if (!$awardRepository->canDispatchToday($award)) {
       throw new DailyLimitExceededException('Daily dispatch limit exceeded');
   }
   ```

3. **Activity Time Validation**
   ```php
   // Verify activity is within valid time period
   if (!$activity->isActive()) {
       throw new ActivityInactiveException('Activity not started or ended');
   }
   ```

## Best Practices

1. **Pool Design**: Group awards by value or type for better management
2. **Probability Configuration**: Use relative weights rather than absolute probabilities
3. **Inventory Management**: Monitor award stock regularly and replenish timely
4. **Audit Logging**: Record all lottery operations for audit purposes
5. **Concurrency Control**: Use transactions and locking to prevent over-allocation

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure PHPStan Level 8 compliance
5. Submit a pull request

## License

This bundle is released under the MIT License.

## Support

For issues and feature requests, please use the project's issue tracker.