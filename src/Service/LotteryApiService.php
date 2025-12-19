<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Award;
use Tourze\RaffleCoreBundle\Entity\Chance;

final readonly class LotteryApiService
{
    public function __construct(
        private ActivityService $activityService,
        private RaffleService $raffleService,
        private ChanceService $chanceService,
        private AwardService $awardService,
    ) {
    }

    /**
     * @return Activity[]
     */
    public function getAvailableActivitiesForUser(UserInterface $user): array
    {
        $activeActivities = $this->activityService->getActiveActivities();

        return array_filter($activeActivities, function (Activity $activity) use ($user) {
            return $this->raffleService->canUserParticipate($user, $activity);
        });
    }

    /**
     * @return array{
     *     activity: Activity,
     *     status: string,
     *     can_participate: bool,
     *     user_chances_count: int,
     *     available_awards: Award[]
     * }
     */
    public function getActivityDetailsForUser(Activity $activity, UserInterface $user): array
    {
        return [
            'activity' => $activity,
            'status' => $this->activityService->getActivityStatus($activity),
            'can_participate' => $this->raffleService->canUserParticipate($user, $activity),
            'user_chances_count' => $this->chanceService->getUserChanceCount($user, $activity),
            'available_awards' => $this->awardService->getAvailableAwardsByActivity($activity),
        ];
    }

    /**
     * @return array{
     *     success: bool,
     *     chance: Chance|null,
     *     award: Award|null,
     *     message: string
     * }
     */
    public function participateAndDraw(UserInterface $user, Activity $activity): array
    {
        try {
            $chance = $this->raffleService->participateInLottery($user, $activity);
            $wonAward = $this->raffleService->drawPrize($chance);

            if (null !== $wonAward) {
                return [
                    'success' => true,
                    'chance' => $chance,
                    'award' => $wonAward,
                    'message' => '恭喜您中奖了！',
                ];
            }

            return [
                'success' => true,
                'chance' => $chance,
                'award' => null,
                'message' => '很遗憾，本次未中奖',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'chance' => null,
                'award' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{
     *     total_participations: int,
     *     winning_count: int,
     *     pending_orders: int,
     *     recent_chances: Chance[]
     * }
     */
    public function getUserLotteryDashboard(UserInterface $user): array
    {
        $allChances = $this->chanceService->getUserWinningHistory($user, 100);
        $winningChances = array_filter($allChances, fn (Chance $chance) => 'winning' === $chance->getStatus()->value);
        $pendingOrders = array_filter($winningChances, fn (Chance $chance) => 'winning' === $chance->getStatus()->value);

        return [
            'total_participations' => count($allChances),
            'winning_count' => count($winningChances),
            'pending_orders' => count($pendingOrders),
            'recent_chances' => array_slice($allChances, 0, 10),
        ];
    }
}
