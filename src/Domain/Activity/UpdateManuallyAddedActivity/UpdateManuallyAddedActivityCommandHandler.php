<?php

declare(strict_types=1);

namespace App\Domain\Activity\UpdateManuallyAddedActivity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\WorldType;
use App\Domain\Image\RemovedImage;
use App\Domain\Import\ImportMode;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Measurement\Velocity\KmPerHour;

final readonly class UpdateManuallyAddedActivityCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private SettingsRepository $settingsRepository,
        private MediaItemStorage $mediaItemStorage,
        private ImportMode $importMode,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateManuallyAddedActivity);

        if (!$this->importMode->isFiles()) {
            throw CouldNotProcessCommand::withReason('Manually added activities can only be updated when running in file import mode.');
        }

        $activityWithRawData = $this->activityRepository->findWithRawData($command->getActivityId());
        if (ImportSource::MANUAL !== $activityWithRawData->getActivity()->getImportSource()) {
            throw CouldNotProcessCommand::withReason('Only manually added activities can be updated this way.');
        }

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $distance = $unitSystem->distance($command->getDistance())->toMeter()->toKilometer();
        $elevation = $unitSystem->elevation($command->getElevation())->toMeter();
        $durationInSeconds = $command->getDurationInSeconds();
        $averageSpeed = KmPerHour::from($distance->toFloat() / ($durationInSeconds / 3600));

        $activity = $activityWithRawData
            ->getActivity()
            ->withName($command->getName())
            ->withDescription($command->getDescription())
            ->withSportType($command->getSportType())
            ->withWorkoutType($command->getWorkoutType())
            ->withDeviceName(null)
            ->withWorldType(WorldType::REAL_WORLD)
            ->withStartDateTime($command->getStartDateTime())
            ->withMovingTimeInSeconds($durationInSeconds)
            ->withElapsedTimeInSeconds($durationInSeconds)
            ->withDistance($distance)
            ->withElevation($elevation)
            ->withAverageSpeed($averageSpeed)
            ->withMaxSpeed($averageSpeed)
            ->withGear($command->getGearId())
            ->withCalories($command->getCalories())
            ->withAverageHeartRate($command->getAverageHeartRate())
            ->withMaxHeartRate($command->getMaxHeartRate())
            ->withCommute($command->isCommute())
            ->withGroupActivity($command->isGroupActivity());

        $newMediaItems = $command->getNewMediaItems();
        $removedMediaItems = $command->getRemovedMediaItems();

        $mediaItemIdsThatNeedRemoval = [];
        if ([] !== $newMediaItems || [] !== $removedMediaItems) {
            $removedMediaItemIds = MediaItemIds::fromArray(array_map(
                static fn (RemovedImage $removedMediaItem): MediaItemId => MediaItemId::fromString($removedMediaItem->getId()),
                $removedMediaItems
            ));

            $retainedMediaItemIds = MediaItemIds::empty();
            foreach ($activity->getMediaItemIds() as $mediaItemId) {
                if ($removedMediaItemIds->has($mediaItemId)) {
                    $mediaItemIdsThatNeedRemoval[] = $mediaItemId;
                    continue;
                }

                $retainedMediaItemIds->add($mediaItemId);
            }

            foreach ($newMediaItems as $newMediaItem) {
                $retainedMediaItemIds->add($this->mediaItemStorage->store(
                    newMediaItem: $newMediaItem,
                    directory: MediaItemDirectory::ACTIVITIES,
                )->getId());
            }

            $activity = $activity->withMediaItemIds($retainedMediaItemIds);
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $activityWithRawData->getRawData(),
        ));

        foreach ($mediaItemIdsThatNeedRemoval as $mediaItemId) {
            $this->mediaItemStorage->remove($mediaItemId);
        }
    }
}
