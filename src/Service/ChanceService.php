<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;

readonly class ChanceService
{
    public function __construct(
        private ChanceRepository $chanceRepository,
    ) {
    }

    /**
     * @return Chance[]
     */
    public function getUserChancesByActivity(UserInterface $user, Activity $activity): array
    {
        return $this->chanceRepository->findByUserAndActivity($user, $activity);
    }

    /**
     * @return Chance[]
     */
    public function getUserWinningHistory(UserInterface $user, int $limit = 10): array
    {
        return $this->chanceRepository->findWinningChancesByUser($user, $limit);
    }

    public function getUserChanceCount(UserInterface $user, Activity $activity): int
    {
        return $this->chanceRepository->countUserChancesInActivity($user, $activity);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function markChanceAsWinning(Chance $chance, Award $award, array $metadata = []): Chance
    {
        if (ChanceStatusEnum::INIT !== $chance->getStatus()) {
            throw new ChanceAlreadyUsedException('抽奖机会已被使用');
        }

        $chance->markAsWinning($award, $metadata);
        $this->chanceRepository->save($chance, true);

        return $chance;
    }

    public function markChanceAsOrdered(Chance $chance): Chance
    {
        if (ChanceStatusEnum::WINNING !== $chance->getStatus()) {
            throw new ChanceAlreadyUsedException('只有中奖状态的机会才能标记为已下单');
        }

        $chance->setStatus(ChanceStatusEnum::ORDERED);
        $this->chanceRepository->save($chance, true);

        return $chance;
    }
}
