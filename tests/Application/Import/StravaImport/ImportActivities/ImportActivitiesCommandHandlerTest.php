<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities;

use App\Application\Import\StravaImport\ImportActivities\ImportActivities;
use App\Application\Import\StravaImport\ImportActivities\ImportActivitiesCommandHandler;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportStep;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\AnalyzeRouteGeography;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\DetermineActivityWeather;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\DownloadActivityImages;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\FetchActivityStreams;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\InitializeActivity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityVisibility;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\BestEffort\ActivityBestEffortBuilder;
use App\Tests\Domain\Activity\Lap\ActivityLapBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use App\Tests\Domain\Strava\SpyStrava;
use App\Tests\Infrastructure\FileSystem\provideAssertFileSystem;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\ProvideSnapshotAssertion;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class ImportActivitiesCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use provideAssertFileSystem;
    use ProvideSnapshotAssertion;

    private ImportActivitiesCommandHandler $importActivitiesCommandHandler;
    private SpyStrava $strava;

    public function testHandleWithTooManyRequestsWhileInitializing(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(0);

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot((string) $output);
    }

    public function testHandleWithTooManyRequestsWhileFetchingActivities(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(12);

        $this->getContainer()->get(GearRepository::class)->add(GearBuilder::fromDefaults()
            ->withGearId(GearId::fromString('gear-b12659861'))
            ->build()
        );

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    Latitude::fromString('51.2'),
                    Longitude::fromString('3.18')
                ))
                ->withTotalImageCount(0)
                ->build(),
            [
                'start_date_local' => '2024-01-01T02:58:29Z',
                'start_latlng' => [51.2, 3.18],
            ]
        ));
        $this->getContainer()->get(ActivityRepository::class)->markActivityStreamsAsImported(ActivityId::fromUnprefixed(4));

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot((string) $output);
        $this->assertFileSystemWrites($this->getContainer()->get('file.storage'));

        $this->assertMatchesJsonSnapshot(Json::encode(
            $this->getConnection()->executeQuery('SELECT * FROM KeyValue')->fetchAllAssociative()
        ));
        $this->assertCompressedDatabaseQueryMatchesSnapshot('SELECT * FROM ActivityStream');
    }

    public function testHandleWithUnexpectedErrorWhileInitializing(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        $this->strava->triggerExceptionOnNextCall();

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));
        $this->assertMatchesTextSnapshot((string) $output);
    }

    public function testHandleWithUnexpectedErrorWhileFetchingActivities(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        $this->strava->triggerExceptionOnNextActivityCall();

        $this->getContainer()->get(GearRepository::class)->add(GearBuilder::fromDefaults()
            ->withGearId(GearId::fromString('gear-b12659861'))
            ->build()
        );

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));
        $this->assertMatchesTextSnapshot((string) $output);
    }

    public function testHandleWithActivityDelete(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1000))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    Latitude::fromString('51.2'),
                    Longitude::fromString('3.18')
                ))
                ->withName('Delete this one')
                ->build(),
            [
                'kudos_count' => 1,
                'name' => 'Delete this one',
            ]
        ));

        $segmentEffortOne = SegmentEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1000))
            ->build();
        $this->getContainer()->get(SegmentEffortRepository::class)->add($segmentEffortOne);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1000))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withName('Delete this one as well')
                ->withActivityId(ActivityId::fromUnprefixed(1001))
                ->build(),
            []
        ));
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed(1000))
                ->withSegmentEffortId(SegmentEffortId::random())
                ->withActivityId(ActivityId::fromUnprefixed(1001))
                ->build()
        );
        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed(1000))
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1001))
                ->build()
        );
        $this->getContainer()->get(ActivitySplitRepository::class)->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1001))
            ->withUnitSystem(UnitSystem::IMPERIAL)
            ->withSplitNumber(3)
            ->build());

        $this->getContainer()->get(ActivityLapRepository::class)->add(ActivityLapBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1001))
            ->build());

        $this->getContainer()->get(ActivityBestEffortRepository::class)->add(ActivityBestEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1001))
            ->build());

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);
        $this->assertCount(
            2,
            $this->getContainer()->get(ActivityIdRepository::class)->findMarkedForDeletion()
        );
    }

    public function testHandleWithDeleteAllActivities(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(100))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1000))
                ->build(),
            []
        ));

        $this->expectExceptionObject(new \RuntimeException('All activities appear to be marked for deletion. This seems like a configuration issue. Aborting to prevent data loss'));
        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));
    }

    public function testHandleWithoutActivityDelete(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->build(), []
        ));

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);
    }

    public function testHandleWhenNoSegmentEffortsDefined(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        $this->strava->returnActivityWithoutSegmentEfforts();

        $this->expectExceptionObject(new \RuntimeException('Activity 2 is expected to include segment_efforts in the raw Strava data. This appears to be a regression introduced in a recent version. Please report this as a bug on GitHub.'));
        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));
    }

    public function testHandleWithActivityVisibilitiesToImport(): void
    {
        $this->importActivitiesCommandHandler = $this->createHandler(
            $this->seedImportSettings(['activityVisibilitiesToImport' => [ActivityVisibility::EVERYONE->value]])
        );

        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->build(), []
        ));

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM KeyValue')->fetchAllAssociative()
        );
    }

    public function testHandleWithTooManyActivitiesToProcessInOneImport(): void
    {
        $this->importActivitiesCommandHandler = $this->createHandler(
            $this->seedImportSettings(['numberOfNewActivitiesToProcessPerImport' => 1])
        );

        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->build(), []
        ));

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM KeyValue')->fetchAllAssociative()
        );

        $this->assertEquals(
            2,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM Activity')->fetchOne()
        );
    }

    public function testHandleWithSkipActivitiesRecordedBefore(): void
    {
        $this->importActivitiesCommandHandler = $this->createHandler(
            $this->seedImportSettings(['skipActivitiesRecordedBefore' => '2023-09-01'])
        );

        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->build(), []
        ));

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);
    }

    public function testHandleWithSportTypeIsNotIncluded(): void
    {
        $this->importActivitiesCommandHandler = $this->createHandler(
            $this->seedImportSettings(['sportTypesToImport' => ['Ride']])
        );

        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withSportType(SportType::VIRTUAL_RIDE)
                ->build(), []
        ));

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);
    }

    public function testHandlePartialImport(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1000))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    Latitude::fromString('51.2'),
                    Longitude::fromString('3.18')
                ))
                ->withName('Delete this one')
                ->build(),
            [
                'kudos_count' => 1,
                'name' => 'Delete this one',
            ]
        ));

        $segmentEffortOne = SegmentEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1000))
            ->build();
        $this->getContainer()->get(SegmentEffortRepository::class)->add($segmentEffortOne);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1000))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withName('Delete this one as well')
                ->withActivityId(ActivityId::fromUnprefixed(1001))
                ->build(),
            []
        ));

        $this->importActivitiesCommandHandler->handle(new ImportActivities($output, ActivityIds::fromArray([ActivityId::fromUnprefixed(4)])));

        $this->assertMatchesTextSnapshot($output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importDataOrBuildApp', 'value' => '{"lockAcquiredBy": "test"}']
        );

        $this->importActivitiesCommandHandler = $this->createHandler(
            $this->getContainer()->get(SettingsRepository::class)
        );
    }

    private function createHandler(SettingsRepository $settingsRepository): ImportActivitiesCommandHandler
    {
        return new ImportActivitiesCommandHandler(
            strava: $this->strava = $this->getContainer()->get(Strava::class),
            activityRepository: $this->getContainer()->get(ActivityRepository::class),
            activityIdRepository: $this->getContainer()->get(ActivityIdRepository::class),
            activityStreamRepository: $this->getContainer()->get(ActivityStreamRepository::class),
            settingsRepository: $settingsRepository,
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString('2025-12-04'),
                lockName: LockName::IMPORT_DATA_OR_BUILD_APP,
            ),
            steps: $this->getPipelineSteps(),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedImportSettings(array $overrides): SettingsRepository
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::IMPORT, [
            'numberOfNewActivitiesToProcessPerImport' => 250,
            'sportTypesToImport' => [],
            'activityVisibilitiesToImport' => [],
            'skipActivitiesRecordedBefore' => null,
            'activitiesToSkipDuringImport' => ['skip'],
            'optInToSegmentDetailImport' => true,
            'webhooks' => ['enabled' => true, 'verifyToken' => 'ffc26d52-d3ff-4797-a2b7-780a593a3547'],
            ...$overrides,
        ]);

        return $settingsRepository;
    }

    /**
     * @return ActivityImportStep[]
     */
    private function getPipelineSteps(): array
    {
        return [
            $this->getContainer()->get(InitializeActivity::class),
            $this->getContainer()->get(FetchActivityStreams::class),
            $this->getContainer()->get(AnalyzeRouteGeography::class),
            $this->getContainer()->get(DetermineActivityWeather::class),
            $this->getContainer()->get(DownloadActivityImages::class),
        ];
    }
}
