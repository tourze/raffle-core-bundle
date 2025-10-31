<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Exception\ActivityInactiveException;

#[Autoconfigure(public: true)]
readonly class LotteryFlowService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityService $activityService,
        private RaffleService $raffleService,
        private ChanceService $chanceService,
        private PrizeOrderService $prizeOrderService,
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     data: array<string, mixed>|null,
     *     message: string
     * }
     */
    public function executeCompleteLotteryFlow(UserInterface $user, Activity $activity): array
    {
        $this->entityManager->beginTransaction();
        try {
            if (!$this->activityService->isActivityActive($activity)) {
                throw new ActivityInactiveException('活动未开始或已结束');
            }

            if (!$this->raffleService->canUserParticipate($user, $activity)) {
                throw new ActivityInactiveException('用户无法参与此活动');
            }

            $chance = $this->raffleService->participateInLottery($user, $activity);
            $wonAward = $this->raffleService->drawPrize($chance);

            $this->entityManager->commit();

            if (null !== $wonAward) {
                $prizeInfo = $this->prizeOrderService->getPrizeOrderInfo($chance);

                return [
                    'success' => true,
                    'data' => [
                        'won' => true,
                        'chance' => $chance,
                        'award' => $wonAward,
                        'prize_info' => $prizeInfo,
                        'need_consignee' => $wonAward->isNeedConsignee(),
                    ],
                    'message' => '恭喜中奖！',
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'won' => false,
                    'chance' => $chance,
                    'award' => null,
                ],
                'message' => '很遗憾，未中奖',
            ];
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $consigneeInfo
     * @return array{
     *     success: bool,
     *     order_data: array<string, mixed>|null,
     *     message: string
     * }
     */
    public function claimPrize(Chance $chance, array $consigneeInfo = []): array
    {
        try {
            if (!$this->prizeOrderService->validatePrizeClaimable($chance)) {
                return [
                    'success' => false,
                    'order_data' => null,
                    'message' => '奖品无法领取或已被领取',
                ];
            }

            $orderData = $this->prizeOrderService->createOrderFromPrize($chance, $consigneeInfo);

            return [
                'success' => true,
                'order_data' => $orderData,
                'message' => '奖品订单创建成功',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'order_data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{
     *     active_activities: Activity[],
     *     user_dashboard: array<string, mixed>,
     *     pending_prizes: Chance[]
     * }
     */
    public function getUserLotteryOverview(UserInterface $user): array
    {
        return [
            'active_activities' => $this->activityService->getActiveActivities(),
            'user_dashboard' => [
                'pending_prizes_count' => count($this->prizeOrderService->getUserPendingPrizes($user)),
                'ordered_prizes_count' => count($this->prizeOrderService->getUserOrderedPrizes($user)),
                'recent_winnings' => $this->chanceService->getUserWinningHistory($user, 5),
            ],
            'pending_prizes' => $this->prizeOrderService->getUserPendingPrizes($user),
        ];
    }
}
