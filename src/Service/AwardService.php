<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Repository\AwardRepository;

final readonly class AwardService
{
    public function __construct(
        private AwardRepository $awardRepository,
    ) {
    }

    /**
     * @return Award[]
     */
    public function getAvailableAwardsByActivity(Activity $activity): array
    {
        return $this->awardRepository->findAvailableByActivity($activity);
    }

    /**
     * @return Award[]
     */
    public function getEligibleAwardsForLottery(Activity $activity): array
    {
        return $this->awardRepository->findEligibleForLotteryByActivity($activity);
    }

    public function checkAwardStock(Award $award): bool
    {
        return $award->getQuantity() > 0;
    }

    public function checkDailyLimit(Award $award): bool
    {
        return $this->awardRepository->canDispatchToday($award);
    }

    public function isAwardEligible(Award $award): bool
    {
        return $award->isValid()
            && $this->checkAwardStock($award)
            && $this->checkDailyLimit($award);
    }

    public function decreaseAwardStock(Award $award, int $amount = 1): bool
    {
        if (!$this->checkAwardStock($award)) {
            return false;
        }

        return $this->awardRepository->decreaseQuantityAtomically($award, $amount);
    }
}
