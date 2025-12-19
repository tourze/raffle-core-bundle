<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ActivityInactiveException;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Repository\AwardRepository;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;

final readonly class RaffleService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AwardRepository $awardRepository,
        private ChanceRepository $chanceRepository,
    ) {
    }

    public function participateInLottery(UserInterface $user, Activity $activity): Chance
    {
        if (!$activity->isActive()) {
            throw new ActivityInactiveException('活动未开始或已结束');
        }

        $chance = new Chance();
        $chance->setActivity($activity);
        $chance->setUser($user);
        $chance->setStatus(ChanceStatusEnum::INIT);

        $this->chanceRepository->save($chance, true);

        return $chance;
    }

    public function drawPrize(Chance $chance): ?Award
    {
        if (ChanceStatusEnum::INIT !== $chance->getStatus()) {
            throw new ChanceAlreadyUsedException('抽奖机会已被使用');
        }

        $activity = $chance->getActivity();
        if (null === $activity || !$activity->isActive()) {
            throw new ActivityInactiveException('活动未开始或已结束');
        }

        $eligibleAwards = $this->awardRepository->findEligibleForLotteryByActivity($activity);

        if (0 === count($eligibleAwards)) {
            $chance->setStatus(ChanceStatusEnum::EXPIRED);
            $this->chanceRepository->save($chance, true);

            return null;
        }

        $wonAward = $this->selectAwardByProbability($eligibleAwards);

        if (null === $wonAward) {
            $chance->setStatus(ChanceStatusEnum::EXPIRED);
            $this->chanceRepository->save($chance, true);

            return null;
        }

        $this->entityManager->beginTransaction();
        try {
            $decreaseSuccess = $this->awardRepository->decreaseQuantityAtomically($wonAward, 1);

            if (!$decreaseSuccess) {
                $this->entityManager->rollback();
                $chance->setStatus(ChanceStatusEnum::EXPIRED);
                $this->chanceRepository->save($chance, true);

                return null;
            }

            $chance->markAsWinning($wonAward, [
                'prize_name' => $wonAward->getName(),
                'win_time' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $this->chanceRepository->save($chance, true);
            $this->entityManager->commit();

            return $wonAward;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @param Award[] $awards
     */
    private function selectAwardByProbability(array $awards): ?Award
    {
        if (0 === count($awards)) {
            return null;
        }

        $totalWeight = array_sum(array_map(
            fn (Award $award) => $this->calculateAwardWeight($award),
            $awards
        ));

        if ($totalWeight <= 0) {
            return null;
        }

        $randomPoint = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($awards as $award) {
            $currentWeight += $this->calculateAwardWeight($award);
            if ($randomPoint <= $currentWeight) {
                return $award;
            }
        }

        return null;
    }

    private function calculateAwardWeight(Award $award): int
    {
        $probability = $award->getProbability();

        if ($probability <= 0) {
            return 0;
        }

        return max(1, (int) (10000 / $probability));
    }

    /**
     * @return Chance[]
     */
    public function getUserLotteryHistory(UserInterface $user, ?Activity $activity = null, int $limit = 20): array
    {
        if (null !== $activity) {
            return $this->chanceRepository->findByUserAndActivity($user, $activity);
        }

        return $this->chanceRepository->findWinningChancesByUser($user, $limit);
    }

    public function canUserParticipate(UserInterface $user, Activity $activity): bool
    {
        if (!$activity->isActive()) {
            return false;
        }

        $eligibleAwards = $this->awardRepository->findEligibleForLotteryByActivity($activity);

        return count($eligibleAwards) > 0;
    }
}
