<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivitySportTypeWasChanged;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class RecombineActivityStreamsListener
{
    public function __construct(
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private ActivityStreamCombiner $activityStreamCombiner,
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[AsEventListener]
    public function reactToActivitySportTypeWasChanged(ActivitySportTypeWasChanged $event): void
    {
        $this->combinedActivityStreamRepository->deleteForActivity($event->getActivityId());
        $this->activityStreamCombiner->combineFor(
            activityId: $event->getActivityId(),
            unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
        );
    }
}
