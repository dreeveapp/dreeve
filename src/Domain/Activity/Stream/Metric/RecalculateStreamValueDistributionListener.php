<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\Metric;

use App\Domain\Activity\ActivitySportTypeWasChanged;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class RecalculateStreamValueDistributionListener
{
    public function __construct(
        private ActivityStreamMetricRepository $activityStreamMetricRepository,
        private ActivityStreamValueDistributionCalculator $activityStreamValueDistributionCalculator,
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[AsEventListener]
    public function reactToActivitySportTypeWasChanged(ActivitySportTypeWasChanged $event): void
    {
        $activityId = $event->getActivityId();
        if ($this->activityStreamMetricRepository->findByActivityIdAndMetricType($activityId, ActivityStreamMetricType::VALUE_DISTRIBUTION)->isEmpty()) {
            return;
        }

        $this->activityStreamMetricRepository->deleteForActivityAndMetricType($activityId, ActivityStreamMetricType::VALUE_DISTRIBUTION);
        $this->activityStreamValueDistributionCalculator->calculateFor(
            activityId: $activityId,
            unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
        );
    }
}
