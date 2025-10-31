<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Service;

use Carbon\CarbonImmutable;
use Tourze\RaffleCoreBundle\Entity\Activity;
use Tourze\RaffleCoreBundle\Repository\ActivityRepository;

readonly class ActivityService
{
    public function __construct(
        private ActivityRepository $activityRepository,
    ) {
    }

    /**
     * @return Activity[]
     */
    public function getActiveActivities(): array
    {
        return $this->activityRepository->findActiveActivities();
    }

    /**
     * @return Activity[]
     */
    public function getUpcomingActivities(int $limit = 10): array
    {
        return $this->activityRepository->findUpcomingActivities($limit);
    }

    public function isActivityActive(Activity $activity): bool
    {
        return $activity->isActive();
    }

    public function getActivityStatus(Activity $activity): string
    {
        if (!$activity->isValid()) {
            return 'inactive';
        }

        $now = CarbonImmutable::now();

        if ($now->lessThan($activity->getStartTime())) {
            return 'upcoming';
        }

        if ($now->greaterThan($activity->getEndTime())) {
            return 'ended';
        }

        return 'active';
    }
}
