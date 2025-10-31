<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Repository\AwardRepository;
use Tourze\RaffleCoreBundle\Service\AwardService;

/**
 * @internal
 */
#[CoversClass(AwardService::class)]
final class AwardServiceTest extends TestCase
{
    private AwardService $awardService;

    /** @var AwardRepository&MockObject */
    private AwardRepository $awardRepository;

    protected function setUp(): void
    {
        $this->awardRepository = $this->createMock(AwardRepository::class);
        $this->awardService = new AwardService($this->awardRepository);
    }

    public function testGetAvailableAwardsByActivityShouldReturnAwardsFromRepository(): void
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $expectedAwards = [
            $this->createAwardWithQuantity('奖品1', 10),
            $this->createAwardWithQuantity('奖品2', 5),
        ];

        $this->awardRepository
            ->expects($this->once())
            ->method('findAvailableByActivity')
            ->with($activity)
            ->willReturn($expectedAwards)
        ;

        $result = $this->awardService->getAvailableAwardsByActivity($activity);

        $this->assertSame($expectedAwards, $result);
        $this->assertCount(2, $result);
    }

    public function testGetEligibleAwardsForLotteryShouldReturnEligibleAwards(): void
    {
        /** @var Activity&MockObject $activity */
        $activity = $this->createMock(Activity::class);
        $expectedAwards = [
            $this->createAwardWithQuantity('可用奖品', 5),
        ];

        $this->awardRepository
            ->expects($this->once())
            ->method('findEligibleForLotteryByActivity')
            ->with($activity)
            ->willReturn($expectedAwards)
        ;

        $result = $this->awardService->getEligibleAwardsForLottery($activity);

        $this->assertSame($expectedAwards, $result);
        $this->assertCount(1, $result);
    }

    public function testCheckAwardStockShouldReturnTrueWhenQuantityGreaterThanZero(): void
    {
        $award = $this->createAwardWithQuantity('奖品', 5);

        $result = $this->awardService->checkAwardStock($award);

        $this->assertTrue($result);
    }

    public function testCheckAwardStockShouldReturnFalseWhenQuantityIsZero(): void
    {
        $award = $this->createAwardWithQuantity('无库存奖品', 0);

        $result = $this->awardService->checkAwardStock($award);

        $this->assertFalse($result);
    }

    public function testCheckDailyLimitShouldReturnRepositoryResult(): void
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);

        $this->awardRepository
            ->expects($this->once())
            ->method('canDispatchToday')
            ->with($award)
            ->willReturn(true)
        ;

        $result = $this->awardService->checkDailyLimit($award);

        $this->assertTrue($result);
    }

    public function testIsAwardEligibleShouldReturnTrueWhenAllConditionsMet(): void
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('isValid')->willReturn(true);
        $award->method('getQuantity')->willReturn(10);

        $this->awardRepository
            ->method('canDispatchToday')
            ->with($award)
            ->willReturn(true)
        ;

        $result = $this->awardService->isAwardEligible($award);

        $this->assertTrue($result);
    }

    public function testIsAwardEligibleShouldReturnFalseWhenAwardIsInvalid(): void
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('isValid')->willReturn(false);
        $award->method('getQuantity')->willReturn(10);

        $this->awardRepository
            ->method('canDispatchToday')
            ->willReturn(true)
        ;

        $result = $this->awardService->isAwardEligible($award);

        $this->assertFalse($result);
    }

    public function testIsAwardEligibleShouldReturnFalseWhenNoStock(): void
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('isValid')->willReturn(true);
        $award->method('getQuantity')->willReturn(0);

        $this->awardRepository
            ->method('canDispatchToday')
            ->willReturn(true)
        ;

        $result = $this->awardService->isAwardEligible($award);

        $this->assertFalse($result);
    }

    public function testIsAwardEligibleShouldReturnFalseWhenDailyLimitReached(): void
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('isValid')->willReturn(true);
        $award->method('getQuantity')->willReturn(10);

        $this->awardRepository
            ->method('canDispatchToday')
            ->with($award)
            ->willReturn(false)
        ;

        $result = $this->awardService->isAwardEligible($award);

        $this->assertFalse($result);
    }

    public function testDecreaseAwardStockShouldReturnFalseWhenNoStock(): void
    {
        $award = $this->createAwardWithQuantity('无库存奖品', 0);

        $result = $this->awardService->decreaseAwardStock($award);

        $this->assertFalse($result);
    }

    public function testDecreaseAwardStockShouldCallRepositoryWhenStockAvailable(): void
    {
        $award = $this->createAwardWithQuantity('有库存奖品', 5);

        $this->awardRepository
            ->expects($this->once())
            ->method('decreaseQuantityAtomically')
            ->with($award, 1)
            ->willReturn(true)
        ;

        $result = $this->awardService->decreaseAwardStock($award);

        $this->assertTrue($result);
    }

    public function testDecreaseAwardStockShouldSupportCustomAmount(): void
    {
        $award = $this->createAwardWithQuantity('奖品', 10);

        $this->awardRepository
            ->expects($this->once())
            ->method('decreaseQuantityAtomically')
            ->with($award, 3)
            ->willReturn(true)
        ;

        $result = $this->awardService->decreaseAwardStock($award, 3);

        $this->assertTrue($result);
    }

    private function createAwardWithQuantity(string $name, int $quantity): Award
    {
        /** @var Award&MockObject $award */
        $award = $this->createMock(Award::class);
        $award->method('getName')->willReturn($name);
        $award->method('getQuantity')->willReturn($quantity);
        $award->method('isValid')->willReturn(true);

        return $award;
    }
}
