<?php

declare(strict_types=1);

namespace App\Domain\Activity\UpdateManuallyAddedActivity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\WorldType;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImagePath;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\RemovedImage;
use App\Domain\Import\ImportMode;
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
        private ImageStorage $imageStorage,
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
            ->withCommute($command->isCommute());

        $newImages = $command->getNewImages();
        $removedImages = $command->getRemovedImages();

        $imagePathsThatNeedRemoval = [];
        if ([] !== $newImages || [] !== $removedImages) {
            $removedLocalImagePaths = array_map(
                static fn (RemovedImage $removedImage): string => $removedImage->getPath()->toLocalImagePath(),
                $removedImages
            );
            $isRemoved = static fn (ImagePath $path): bool => in_array($path->toLocalImagePath(), $removedLocalImagePaths, true);

            $currentPaths = array_map(
                ImagePath::fromLocalImagePath(...),
                $activity->getLocalImagePaths()
            );

            $retainedPaths = array_values(array_filter($currentPaths, static fn (ImagePath $path): bool => !$isRemoved($path)));
            $imagePathsThatNeedRemoval = array_values(array_filter($currentPaths, $isRemoved));

            foreach ($newImages as $newImage) {
                $retainedPaths[] = $this->imageStorage->store(
                    newImage: $newImage,
                    directory: ImageDirectory::ACTIVITIES
                );
            }

            $activity = $activity->withLocalImagePaths(array_map(
                static fn (ImagePath $path): string => $path->toLocalImagePath(),
                $retainedPaths
            ));
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $activityWithRawData->getRawData(),
        ));

        foreach ($imagePathsThatNeedRemoval as $path) {
            $this->imageStorage->remove($path);
        }
    }
}
