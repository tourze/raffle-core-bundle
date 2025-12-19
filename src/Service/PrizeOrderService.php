<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\RaffleCoreBundle\Entity\Chance;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Exception\ChanceAlreadyUsedException;
use Tourze\RaffleCoreBundle\Exception\InvalidPrizeException;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;

final readonly class PrizeOrderService
{
    public function __construct(
        private ChanceRepository $chanceRepository,
        private ChanceService $chanceService,
    ) {
    }

    /**
     * @return Chance[]
     */
    public function getUserPendingPrizes(UserInterface $user): array
    {
        return $this->chanceRepository->findBy([
            'user' => $user,
            'status' => ChanceStatusEnum::WINNING,
        ]);
    }

    /**
     * @return array{
     *     chance_id: int,
     *     award_name: string,
     *     award_value: string,
     *     need_consignee: bool,
     *     win_time: \DateTimeImmutable
     * }
     */
    public function getPrizeOrderInfo(Chance $chance): array
    {
        if (ChanceStatusEnum::WINNING !== $chance->getStatus()) {
            throw new ChanceAlreadyUsedException('该中奖记录状态不正确');
        }

        $award = $chance->getAward();
        if (null === $award) {
            throw new InvalidPrizeException('中奖记录没有关联奖品');
        }

        $chanceId = $chance->getId();
        if (null === $chanceId) {
            throw new InvalidPrizeException('中奖记录ID无效');
        }

        $winTime = $chance->getUseTime() ?? $chance->getCreateTime();
        if (null === $winTime) {
            throw new InvalidPrizeException('中奖记录时间无效');
        }

        return [
            'chance_id' => $chanceId,
            'award_name' => $award->getName(),
            'award_value' => $award->getValue(),
            'need_consignee' => $award->isNeedConsignee(),
            'win_time' => $winTime,
        ];
    }

    /**
     * @param array<string, mixed> $consigneeInfo
     * @return array<string, mixed>
     */
    public function createOrderFromPrize(Chance $chance, array $consigneeInfo = []): array
    {
        if (ChanceStatusEnum::WINNING !== $chance->getStatus()) {
            throw new ChanceAlreadyUsedException('该中奖记录已处理或状态不正确');
        }

        $award = $chance->getAward();
        if (null === $award) {
            throw new InvalidPrizeException('中奖记录没有关联奖品');
        }

        $this->chanceService->markChanceAsOrdered($chance);

        return [
            'chance_id' => $chance->getId(),
            'award_name' => $award->getName(),
            'award_value' => $award->getValue(),
            'consignee_info' => $consigneeInfo,
            'order_time' => new \DateTimeImmutable(),
        ];
    }

    public function validatePrizeClaimable(Chance $chance): bool
    {
        if (ChanceStatusEnum::WINNING !== $chance->getStatus()) {
            return false;
        }

        $award = $chance->getAward();
        if (null === $award) {
            return false;
        }

        return $award->isValid();
    }

    /**
     * @return Chance[]
     */
    public function getUserOrderedPrizes(UserInterface $user, int $limit = 20): array
    {
        return $this->chanceRepository->findBy([
            'user' => $user,
            'status' => ChanceStatusEnum::ORDERED,
        ], ['useTime' => 'DESC'], $limit);
    }
}
