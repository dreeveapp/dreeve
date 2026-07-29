<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\UpdateManuallyAddedActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\UpdateManuallyAddedActivity\UpdateManuallyAddedActivity;
use App\Domain\Activity\UpdateManuallyAddedActivity\UpdateManuallyAddedActivityCommandHandler;
use App\Domain\Activity\WorkoutType;
use App\Domain\Activity\WorldType;
use App\Domain\Gear\GearId;
use App\Domain\Image\ImageStorage;
use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use League\Flysystem\FilesystemOperator;

class UpdateManuallyAddedActivityCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $fileStorage;

    public function testHandle(): void
    {
        $this->addManuallyAddedActivity();

        $this->commandBus->dispatch(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My updated manual activity',
            'description' => 'A nice run',
            'sportType' => 'Run',
            'workoutType' => 'race',
            'startDateTime' => '2023-10-18T07:30',
            'duration' => ['hours' => 0, 'minutes' => 50, 'seconds' => 0],
            'distance' => '10',
            'elevation' => '120',
            'gearId' => 'gear-1',
            'isCommute' => 'true',
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));

        $this->assertSame('My updated manual activity', $activity->getOriginalName());
        $this->assertSame('A nice run', $activity->getDescription());
        $this->assertSame(SportType::RUN, $activity->getSportType());
        $this->assertSame(WorkoutType::RACE, $activity->getWorkoutType());
        $this->assertSame(ImportSource::MANUAL, $activity->getImportSource());
        $this->assertSame(WorldType::REAL_WORLD, $activity->getWorldType());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-18 07:30:00'), $activity->getStartDate());
        $this->assertSame(10.0, $activity->getDistance()->toFloat());
        $this->assertSame(120.0, $activity->getElevation()->toFloat());
        $this->assertSame(3000, $activity->getMovingTimeInSeconds());
        $this->assertSame(3000, $activity->getElapsedTimeInSeconds());
        $this->assertSame(12.0, $activity->getAverageSpeed()->toFloat());
        $this->assertSame(12.0, $activity->getMaxSpeed()->toFloat());
        $this->assertEquals(GearId::fromUnprefixed('1'), $activity->getGearId());
        $this->assertNull($activity->getDeviceName());
        $this->assertTrue($activity->isCommute());
    }

    public function testHandleClearsOptionalFields(): void
    {
        $this->addManuallyAddedActivity();

        $this->commandBus->dispatch(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My updated manual activity',
            'sportType' => 'Workout',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));

        $this->assertSame('', $activity->getDescription());
        $this->assertNull($activity->getDeviceName());
        $this->assertNull($activity->getGearId());
        $this->assertNull($activity->getWorkoutType());
        $this->assertFalse($activity->isCommute());
        $this->assertSame(0.0, $activity->getDistance()->toFloat());
        $this->assertSame(0.0, $activity->getAverageSpeed()->toFloat());
    }

    public function testHandleConvertsImperialInput(): void
    {
        $this->provideAppearanceSettingsWithUnitSystem('imperial');
        $this->addManuallyAddedActivity();

        $this->commandBus->dispatch(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My updated manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['hours' => 1],
            'distance' => '10',
            'elevation' => '100',
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));

        $this->assertSame(16.093, round($activity->getDistance()->toFloat(), 3));
        $this->assertSame(30.0, round($activity->getElevation()->toFloat(), 1));
        $this->assertSame(16.093, round($activity->getAverageSpeed()->toFloat(), 3));
    }

    public function testHandleKeepsTheActivityInTheRealWorld(): void
    {
        $this->addManuallyAddedActivity();

        $this->commandBus->dispatch(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            // A virtual platform in the name does not make this a virtual activity.
            'name' => 'My MyWhoosh ride',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));

        $this->assertSame(WorldType::REAL_WORLD, $activity->getWorldType());
    }

    public function testHandleAddsNewAndRemovesImagesWhileKeepingTheRest(): void
    {
        $this->fileStorage->write('activities/keep.png', 'keep');
        $this->fileStorage->write('activities/drop.png', 'drop');
        $this->addManuallyAddedActivity('files/activities/keep.png', 'files/activities/drop.png');

        $this->commandBus->dispatch(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My updated manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
            'images' => Json::encode([
                ['status' => 'removed', 'path' => '/files/activities/drop.png'],
                ['status' => 'new', 'filename' => 'new.jpg', 'content' => base64_encode('new-content')],
            ]),
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));

        $localImagePaths = $activity->getLocalImagePaths();
        $this->assertCount(2, $localImagePaths);
        $this->assertSame(2, $activity->getTotalImageCount());
        $this->assertSame('/files/activities/keep.png', $localImagePaths[0]);
        $this->assertStringEndsWith('.jpg', $localImagePaths[1]);

        $this->assertFalse($this->fileStorage->fileExists('activities/drop.png'));
        $this->assertTrue($this->fileStorage->fileExists('activities/keep.png'));
        $newRelativePath = ltrim((string) preg_replace('#^/files/#', '', $localImagePaths[1]), '/');
        $this->assertSame('new-content', $this->fileStorage->read($newRelativePath));
    }

    public function testHandleLeavesImagesUntouchedWhenNothingChanged(): void
    {
        $this->fileStorage->write('activities/existing.png', 'binary');
        $this->addManuallyAddedActivity('files/activities/existing.png');

        $this->commandBus->dispatch(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My updated manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
            'images' => '[]',
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));

        $this->assertSame(['/files/activities/existing.png'], $activity->getLocalImagePaths());
        $this->assertTrue($this->fileStorage->fileExists('activities/existing.png'));
    }

    public function testHandleThrowsWhenTheActivityWasNotAddedManually(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withImportSource(ImportSource::FIT_FILE)
                ->build(),
            rawData: [],
        ));

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Only manually added activities can be updated this way.'));

        $this->commandBus->dispatch(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My updated manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
        ]));
    }

    public function testHandleThrowsWhenNotRunningInFileImportMode(): void
    {
        $this->addManuallyAddedActivity();

        $handler = new UpdateManuallyAddedActivityCommandHandler(
            activityRepository: $this->activityRepository,
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
            imageStorage: $this->getContainer()->get(ImageStorage::class),
            importMode: ImportMode::STRAVA_API,
        );

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Manually added activities can only be updated when running in file import mode.'));

        $handler->handle(UpdateManuallyAddedActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'My updated manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
        ]));
    }

    private function addManuallyAddedActivity(string ...$localImagePaths): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withImportSource(ImportSource::MANUAL)
            ->withName('My manual activity')
            ->withSportType(SportType::RIDE)
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-17 16:15:00'))
            // A device name can only linger from before manual activities dropped the field.
            ->withDeviceName('Garmin Edge')
            ->withMovingTimeInSeconds(3000)
            ->withDistance(Kilometer::from(10))
            ->withElevation(Meter::from(120));

        if ([] !== $localImagePaths) {
            $activity = $activity->withLocalImagePaths(...$localImagePaths);
        }

        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity->build(),
            rawData: [],
        ));
    }

    private function provideAppearanceSettingsWithUnitSystem(string $unitSystem): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::APPEARANCE->keyValueKey(),
            Value::fromString(Json::encode([
                'locale' => 'en_US',
                'unitSystem' => $unitSystem,
                'timeFormat' => 24,
                'dateFormat' => ['short' => 'd-m-y', 'normal' => 'd-m-Y'],
            ])),
        ));
    }

    #[\Override]
    protected function importMode(): ImportMode
    {
        return ImportMode::FILES;
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
