<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\ManuallyCreateActivity;

use App\Domain\Activity\ActivityIdFactory;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\ManuallyCreateActivity\ManuallyCreateActivity;
use App\Domain\Activity\ManuallyCreateActivity\ManuallyCreateActivityCommandHandler;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Activity\WorldType;
use App\Domain\Gear\GearId;
use App\Domain\Import\ImportMode;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItems;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Media\MediaItemBuilder;
use League\Flysystem\FilesystemOperator;

class ManuallyCreateActivityCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $fileStorage;

    public function testHandle(): void
    {
        $this->commandBus->dispatch(ManuallyCreateActivity::fromPayload([
            'name' => 'My manual activity',
            'description' => 'A nice run',
            'sportType' => 'Run',
            'workoutType' => 'race',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['hours' => 0, 'minutes' => 50, 'seconds' => 0],
            'distance' => '10',
            'elevation' => '120',
            'calories' => '750',
            'averageHeartRate' => '140',
            'maxHeartRate' => '172',
            'gearId' => 'gear-1',
            'isCommute' => 'true',
            'isGroupActivity' => 'true',
        ]));

        $activity = $this->activityRepository->findAll()->getFirst();
        $this->assertNotNull($activity);

        $this->assertSame('My manual activity', $activity->getOriginalName());
        $this->assertSame('A nice run', $activity->getDescription());
        $this->assertSame(SportType::RUN, $activity->getSportType());
        $this->assertSame(WorkoutType::RACE, $activity->getWorkoutType());
        $this->assertSame(ImportSource::MANUAL, $activity->getImportSource());
        $this->assertSame(WorldType::REAL_WORLD, $activity->getWorldType());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:00'), $activity->getStartDate());
        $this->assertSame(10.0, $activity->getDistance()->toFloat());
        $this->assertSame(120.0, $activity->getElevation()->toFloat());
        $this->assertSame(3000, $activity->getMovingTimeInSeconds());
        $this->assertSame(3000, $activity->getElapsedTimeInSeconds());
        $this->assertSame(12.0, $activity->getAverageSpeed()->toFloat());
        $this->assertSame(12.0, $activity->getMaxSpeed()->toFloat());
        $this->assertEquals(GearId::fromUnprefixed('1'), $activity->getGearId());
        $this->assertSame(750, $activity->getCalories());
        $this->assertSame(140, $activity->getAverageHeartRate());
        $this->assertSame(172, $activity->getMaxHeartRate());
        $this->assertNull($activity->getDeviceName());
        $this->assertTrue($activity->isCommute());
        $this->assertTrue($activity->isGroupActivity());
        $this->assertNull($activity->getEncodedPolyline());
        $this->assertNull($activity->getStartingCoordinate());
        $this->assertTrue($activity->getMediaItemIds()->isEmpty());
        $this->assertSame(0, $activity->getTotalImageCount());
        $this->assertTrue($this->getContainer()->get(ActivityScanRepository::class)->isScanned(
            activityId: $activity->getId(),
            type: ActivityScanType::STREAMS,
        ));
    }

    public function testHandleWithoutOptionalFields(): void
    {
        $this->commandBus->dispatch(ManuallyCreateActivity::fromPayload([
            'name' => 'My manual activity',
            'sportType' => 'Workout',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
        ]));

        $activity = $this->activityRepository->findAll()->getFirst();
        $this->assertNotNull($activity);

        $this->assertSame('', $activity->getDescription());
        $this->assertNull($activity->getCalories());
        $this->assertNull($activity->getDeviceName());
        $this->assertNull($activity->getGearId());
        $this->assertNull($activity->getWorkoutType());
        $this->assertNull($activity->getAverageHeartRate());
        $this->assertNull($activity->getMaxHeartRate());
        $this->assertNull($activity->getAveragePower());
        $this->assertFalse($activity->isCommute());
        $this->assertFalse($activity->isGroupActivity());
        $this->assertSame(0.0, $activity->getDistance()->toFloat());
        $this->assertSame(0.0, $activity->getAverageSpeed()->toFloat());
    }

    public function testHandleConvertsImperialInput(): void
    {
        $this->provideAppearanceSettingsWithUnitSystem('imperial');

        $this->commandBus->dispatch(ManuallyCreateActivity::fromPayload([
            'name' => 'My manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['hours' => 1],
            'distance' => '10',
            'elevation' => '100',
        ]));

        $activity = $this->activityRepository->findAll()->getFirst();
        $this->assertNotNull($activity);

        $this->assertSame(16.093, round($activity->getDistance()->toFloat(), 3));
        $this->assertSame(30.0, round($activity->getElevation()->toFloat(), 1));
        $this->assertSame(16.093, round($activity->getAverageSpeed()->toFloat(), 3));
    }

    public function testHandleStoresImages(): void
    {
        $this->commandBus->dispatch(ManuallyCreateActivity::fromPayload([
            'name' => 'My manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
            'images' => Json::encode([
                ['status' => 'new', 'filename' => 'photo.jpg', 'content' => base64_encode('binary-content')],
            ]),
        ]));

        $activity = $this->activityRepository->findAll()->getFirst();
        $this->assertNotNull($activity);

        $this->assertEquals(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5')]), $activity->getMediaItemIds());
        $this->assertSame(1, $activity->getTotalImageCount());
        $this->assertTrue($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
        $this->assertEquals(
            MediaItems::fromArray([
                MediaItemBuilder::fromDefaults()
                    ->withMediaItemId(MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'))
                    ->withPath(MediaItemPath::fromLocalPath('files/activities/0025176c-5652-11ee-923d-02424dd627d5.jpg'))
                    ->build(),
            ]),
            $this->getContainer()->get(MediaItemRepository::class)->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5')])),
        );
    }

    public function testHandleThrowsWhenNotRunningInFileImportMode(): void
    {
        $handler = new ManuallyCreateActivityCommandHandler(
            activityRepository: $this->activityRepository,
            activityScanRepository: $this->getContainer()->get(ActivityScanRepository::class),
            activityIdFactory: $this->getContainer()->get(ActivityIdFactory::class),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
            mediaItemStorage: $this->getContainer()->get(MediaItemStorage::class),
            importMode: ImportMode::STRAVA_API,
        );

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Activities can only be created manually when running in file import mode.'));

        $handler->handle(ManuallyCreateActivity::fromPayload([
            'name' => 'My manual activity',
            'sportType' => 'Run',
            'startDateTime' => '2023-10-17T16:15',
            'duration' => ['minutes' => 30],
        ]));

        $this->assertTrue($this->activityRepository->findAll()->isEmpty());
    }

    private function provideAppearanceSettingsWithUnitSystem(string $unitSystem): void
    {
        /** @var DbalSettingsRepository $settingsRepository */
        $settingsRepository = $this->getContainer()->get(DbalSettingsRepository::class);
        $settingsRepository->saveGroup(SettingsGroup::APPEARANCE, [
            'locale' => 'en_US',
            'unitSystem' => $unitSystem,
            'timeFormat' => 24,
            'dateFormat' => ['short' => 'd-m-y', 'normal' => 'd-m-Y'],
        ]);
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
