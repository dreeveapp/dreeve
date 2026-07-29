<?php

declare(strict_types=1);

namespace App\Domain\Activity\ManuallyCreateActivity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityIdFactory;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\WorldType;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Measurement\Velocity\KmPerHour;

final readonly class ManuallyCreateActivityCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityIdFactory $activityIdFactory,
        private SettingsRepository $settingsRepository,
        private ImageStorage $imageStorage,
        private ImportMode $importMode,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ManuallyCreateActivity);

        if (!$this->importMode->isFiles()) {
            throw CouldNotProcessCommand::withReason('Activities can only be created manually when running in file import mode.');
        }

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $distance = $unitSystem->distance($command->getDistance())->toMeter()->toKilometer();
        $elevation = $unitSystem->elevation($command->getElevation())->toMeter();
        $durationInSeconds = $command->getDurationInSeconds();
        $averageSpeed = KmPerHour::from($distance->toFloat() / ($durationInSeconds / 3600));

        $activityId = $this->activityIdFactory->random();
        $activity = Activity::fromState(
            activityId: $activityId,
            startDateTime: $command->getStartDateTime(),
            sportType: $command->getSportType(),
            worldType: WorldType::REAL_WORLD,
            importSource: ImportSource::MANUAL,
            externalReferenceId: null,
            name: $command->getName(),
            description: $command->getDescription(),
            distance: $distance,
            elevation: $elevation,
            startingCoordinate: null,
            calories: null,
            kilojoules: null,
            averagePower: null,
            maxPower: null,
            averageSpeed: $averageSpeed,
            maxSpeed: $averageSpeed,
            averageHeartRate: null,
            maxHeartRate: null,
            averageCadence: null,
            movingTimeInSeconds: $durationInSeconds,
            elapsedTimeInSeconds: $durationInSeconds,
            deviceName: null,
            totalImageCount: 0,
            localImagePaths: [],
            polyline: null,
            routeGeography: RouteGeography::create([]),
            weather: null,
            gearId: $command->getGearId(),
            isCommute: $command->isCommute(),
            workoutType: $command->getWorkoutType(),
        );

        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: [],
        ));
        $this->activityRepository->markActivityStreamsAsImported($activityId);

        $localImagePaths = array_map(
            fn (NewImage $newImage): string => $this->imageStorage->store(
                newImage: $newImage,
                directory: ImageDirectory::ACTIVITIES
            )->toLocalImagePath(),
            $command->getNewImages()
        );

        if ([] === $localImagePaths) {
            return;
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity->withLocalImagePaths($localImagePaths),
            rawData: [],
        ));
    }
}
