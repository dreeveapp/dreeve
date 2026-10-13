<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivitySportTypeWasChanged;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class RecalculateBestEffortsListener
{
    public function __construct(
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ActivityBestEffortCalculator $activityBestEffortCalculator,
    ) {
    }

    #[AsEventListener]
    public function reactToActivitySportTypeWasChanged(ActivitySportTypeWasChanged $event): void
    {
        $this->activityBestEffortRepository->deleteForActivity($event->getActivityId());
        $this->activityBestEffortCalculator->calculateFor($event->getActivityId());
    }
}
