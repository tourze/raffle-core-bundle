<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;
use Tourze\RaffleCoreBundle\Service\ChanceService;

/**
 * @internal
 */
#[CoversClass(ChanceService::class)]
final class ChanceServiceTest extends TestCase
{
    private ChanceService $chanceService;

    /** @var ChanceRepository&MockObject */
    private ChanceRepository $chanceRepository;

    protected function setUp(): void
    {
        /** @var ChanceRepository&MockObject $chanceRepository */
        $chanceRepository = $this->createMock(ChanceRepository::class);
        $this->chanceRepository = $chanceRepository;
        $this->chanceService = new ChanceService($this->chanceRepository);
    }

    public function testGetUserChancesByActivityShouldReturnChancesFromRepository(): void
    {
        /** @var UserInterface&MockObject */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject */
        $activity = $this->createMock(Activity::class);
        $expectedChances = [
            $this->createChanceWithStatus(ChanceStatusEnum::INIT),
            $this->createChanceWithStatus(ChanceStatusEnum::WINNING),
        ];

        $this->chanceRepository
            ->expects($this->once())
            ->method('findByUserAndActivity')
            ->with($user, $activity)
            ->willReturn($expectedChances)
        ;

        $result = $this->chanceService->getUserChancesByActivity($user, $activity);

        $this->assertSame($expectedChances, $result);
        $this->assertCount(2, $result);
    }

    public function testGetUserWinningHistoryShouldReturnWinningChances(): void
    {
        /** @var UserInterface&MockObject */
        $user = $this->createMock(UserInterface::class);
        $expectedChances = [
            $this->createChanceWithStatus(ChanceStatusEnum::WINNING),
        ];

        $this->chanceRepository
            ->expects($this->once())
            ->method('findWinningChancesByUser')
            ->with($user, 5)
            ->willReturn($expectedChances)
        ;

        $result = $this->chanceService->getUserWinningHistory($user, 5);

        $this->assertSame($expectedChances, $result);
        $this->assertCount(1, $result);
    }

    public function testGetUserWinningHistoryShouldUseDefaultLimit(): void
    {
        /** @var UserInterface&MockObject */
        $user = $this->createMock(UserInterface::class);

        $this->chanceRepository
            ->expects($this->once())
            ->method('findWinningChancesByUser')
            ->with($user, 10)
            ->willReturn([])
        ;

        $this->chanceService->getUserWinningHistory($user);
    }

    public function testGetUserChanceCountShouldReturnCountFromRepository(): void
    {
        /** @var UserInterface&MockObject */
        $user = $this->createMock(UserInterface::class);
        /** @var Activity&MockObject */
        $activity = $this->createMock(Activity::class);

        $this->chanceRepository
            ->expects($this->once())
            ->method('countUserChancesInActivity')
            ->with($user, $activity)
            ->willReturn(3)
        ;

        $result = $this->chanceService->getUserChanceCount($user, $activity);

        $this->assertSame(3, $result);
    }

    public function testMarkChanceAsWinningShouldSucceedForInitStatus(): void
    {
        /** @var Chance&MockObject */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject */
        $award = $this->createMock(Award::class);
        $metadata = ['prize_name' => '测试奖品'];

        $chance->expects($this->once())
            ->method('getStatus')
            ->willReturn(ChanceStatusEnum::INIT)
        ;

        $chance->expects($this->once())
            ->method('markAsWinning')
            ->with($award, $metadata)
        ;

        $this->chanceRepository
            ->expects($this->once())
            ->method('save')
            ->with($chance, true)
        ;

        $result = $this->chanceService->markChanceAsWinning($chance, $award, $metadata);

        $this->assertSame($chance, $result);
    }

    public function testMarkChanceAsWinningShouldThrowExceptionForNonInitStatus(): void
    {
        /** @var Chance&MockObject */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject */
        $award = $this->createMock(Award::class);

        $chance->expects($this->once())
            ->method('getStatus')
            ->willReturn(ChanceStatusEnum::WINNING)
        ;

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('抽奖机会已被使用');

        $this->chanceService->markChanceAsWinning($chance, $award);
    }

    public function testMarkChanceAsOrderedShouldSucceedForWinningStatus(): void
    {
        /** @var Chance&MockObject */
        $chance = $this->createMock(Chance::class);

        $chance->expects($this->once())
            ->method('getStatus')
            ->willReturn(ChanceStatusEnum::WINNING)
        ;

        $chance->expects($this->once())
            ->method('setStatus')
            ->with(ChanceStatusEnum::ORDERED)
        ;

        $this->chanceRepository
            ->expects($this->once())
            ->method('save')
            ->with($chance, true)
        ;

        $result = $this->chanceService->markChanceAsOrdered($chance);

        $this->assertSame($chance, $result);
    }

    public function testMarkChanceAsOrderedShouldThrowExceptionForNonWinningStatus(): void
    {
        /** @var Chance&MockObject */
        $chance = $this->createMock(Chance::class);

        $chance->expects($this->once())
            ->method('getStatus')
            ->willReturn(ChanceStatusEnum::INIT)
        ;

        $this->expectException(ChanceAlreadyUsedException::class);
        $this->expectExceptionMessage('只有中奖状态的机会才能标记为已下单');

        $this->chanceService->markChanceAsOrdered($chance);
    }

    public function testMarkChanceAsWinningShouldUseEmptyMetadataByDefault(): void
    {
        /** @var Chance&MockObject */
        $chance = $this->createMock(Chance::class);
        /** @var Award&MockObject */
        $award = $this->createMock(Award::class);

        $chance->method('getStatus')->willReturn(ChanceStatusEnum::INIT);

        $chance->expects($this->once())
            ->method('markAsWinning')
            ->with($award, [])
        ;

        $this->chanceRepository->method('save');

        $this->chanceService->markChanceAsWinning($chance, $award);
    }

    private function createChanceWithStatus(ChanceStatusEnum $status): Chance
    {
        /** @var Chance&MockObject */
        $chance = $this->createMock(Chance::class);
        $chance->method('getStatus')->willReturn($status);

        return $chance;
    }
}
