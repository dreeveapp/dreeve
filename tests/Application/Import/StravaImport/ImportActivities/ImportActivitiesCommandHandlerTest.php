<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities;

use App\Application\Import\StravaImport\ImportActivities\ImportActivities;
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
use App\Domain\Gear\GearType;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
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
use App\Tests\SpyOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class ImportActivitiesCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use provideAssertFileSystem;

    private CommandBus $commandBus;
    private SpyStrava $strava;

    public function testHandleWithTooManyRequestsWhileInitializing(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(0);

        $this->commandBus->dispatch(new ImportActivities($output, null));

        $this->assertSame(
            "Importing activities...\n"
            .'<error>You reached the daily Strava API rate limit. You will need to import the rest of your data tomorrow</error>',
            (string) $output,
        );
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

        $this->commandBus->dispatch(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot((string) $output);
        $this->assertFileSystemWrites($this->getContainer()->get('file.storage'));

        $this->assertSame(
            [['key' => 'lock.importData', 'value' => '{"heartbeat":1697559304,"lockAcquiredBy":"test"}']],
            $this->getConnection()->executeQuery('SELECT `key`, `value` FROM KeyValue')->fetchAllAssociative()
        );
        $this->assertSame(
            [
                ['activityId' => 'activity-2', 'streamType' => 'watts'],
                ['activityId' => 'activity-2', 'streamType' => 'distance'],
                ['activityId' => 'activity-3', 'streamType' => 'watts'],
                ['activityId' => 'activity-3', 'streamType' => 'distance'],
            ],
            $this->getConnection()->executeQuery('SELECT activityId, streamType FROM ActivityStream')->fetchAllAssociative()
        );
    }

    public function testHandleWithUnexpectedErrorWhileInitializing(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        $this->strava->triggerExceptionOnNextCall();

        $this->commandBus->dispatch(new ImportActivities($output, null));
        $this->assertSame(
            "Importing activities...\n"
            .'<error>Strava API threw error: The error</error>',
            (string) $output,
        );
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

        $this->commandBus->dispatch(new ImportActivities($output, null));
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

        $this->commandBus->dispatch(new ImportActivities($output, null));

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
        $this->commandBus->dispatch(new ImportActivities($output, null));
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

        $this->commandBus->dispatch(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);
    }

    #[DataProvider('provideGearAssignments')]
    public function testHandleAssignsGear(?GearType $existingGearType, string $assignedGearId, bool $stravaHasGear, ?string $expectedGearId): void
    {
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        if (!$stravaHasGear) {
            $this->strava->emptyGearIdOnActivities();
        }

        if ($existingGearType instanceof GearType) {
            $this->getContainer()->get(GearRepository::class)->add(GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed($assignedGearId))
                ->withGearType($existingGearType)
                ->build()
            );
        }

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withGearId(GearId::fromUnprefixed($assignedGearId))
                ->build(), []
        ));

        $this->commandBus->dispatch(new ImportActivities(new SpyOutput(), null));

        $this->assertEquals(
            null === $expectedGearId ? null : GearId::fromUnprefixed($expectedGearId),
            $this->getContainer()->get(ActivityRepository::class)->find(ActivityId::fromUnprefixed(4))->getGearId()
        );
    }

    public static function provideGearAssignments(): iterable
    {
        yield 'custom gear is kept when strava has no gear' => [GearType::CUSTOM, 'custom-one', false, 'custom-one'];
        yield 'strava gear wins over custom gear' => [GearType::CUSTOM, 'custom-one', true, 'b12659861'];
        yield 'strava gear overwrites imported gear' => [GearType::IMPORTED, 'b12659743', true, 'b12659861'];
        yield 'gear that does not exist is emptied' => [null, 'does-not-exist', false, null];
    }

    public function testHandleWhenNoSegmentEffortsDefined(): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        $this->strava->returnActivityWithoutSegmentEfforts();

        $this->expectExceptionObject(new \RuntimeException('Activity 2 is expected to include segment_efforts in the raw Strava data. This appears to be a regression introduced in a recent version. Please report this as a bug on GitHub.'));
        $this->commandBus->dispatch(new ImportActivities($output, null));
    }

    public function testHandleWithActivityVisibilitiesToImport(): void
    {
        $this->seedImportSettings(['activityVisibilitiesToImport' => [ActivityVisibility::EVERYONE->value]]);

        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->build(), []
        ));

        $this->commandBus->dispatch(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);

        $this->assertSame(
            [['key' => 'lock.importData', 'value' => '{"heartbeat":1697559304,"lockAcquiredBy":"test"}']],
            $this->getConnection()->executeQuery('SELECT `key`, `value` FROM KeyValue')->fetchAllAssociative()
        );
    }

    public function testHandleWithTooManyActivitiesToProcessInOneImport(): void
    {
        $this->seedImportSettings(['numberOfNewActivitiesToProcessPerImport' => 1]);

        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->build(), []
        ));

        $this->commandBus->dispatch(new ImportActivities($output, null));

        $this->assertMatchesTextSnapshot($output);

        $this->assertSame(
            [['key' => 'lock.importData', 'value' => '{"heartbeat":1697559304,"lockAcquiredBy":"test"}']],
            $this->getConnection()->executeQuery('SELECT `key`, `value` FROM KeyValue')->fetchAllAssociative()
        );

        $this->assertEquals(
            2,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM Activity')->fetchOne()
        );
    }

    public function testHandleAppliesTheMaxNumberOfActivitiesToProcessToEachImport(): void
    {
        $this->seedImportSettings(['numberOfNewActivitiesToProcessPerImport' => 1]);
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->build(), []
        ));

        $this->commandBus->dispatch(new ImportActivities(new SpyOutput(), null));
        $this->commandBus->dispatch(new ImportActivities(new SpyOutput(), null));

        $this->assertEquals(
            3,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM Activity')->fetchOne()
        );
    }

    /**
     * @param array<string, mixed> $importSettings
     */
    #[DataProvider('provideImportSettingsThatSkipTheVirtualRide')]
    public function testHandleSkipsActivitiesExcludedByTheImportSettings(array $importSettings, SportType $sportTypeOfExistingActivity): void
    {
        $this->seedImportSettings($importSettings);

        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withSportType($sportTypeOfExistingActivity)
                ->build(), []
        ));

        $this->commandBus->dispatch(new ImportActivities($output, null));

        $this->assertStringNotContainsString('Watopia Flat Forward in London', (string) $output);
        $this->assertStringEndsWith('  => [4/7] Imported activity: "Night Ride5 - 11-09-2023"', (string) $output);
    }

    public static function provideImportSettingsThatSkipTheVirtualRide(): iterable
    {
        yield 'recorded before the cutoff date' => [['skipActivitiesRecordedBefore' => '2023-09-01'], SportType::RIDE];
        yield 'sport type is not included' => [['sportTypesToImport' => ['Ride']], SportType::VIRTUAL_RIDE];
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

        $this->commandBus->dispatch(new ImportActivities($output, ActivityIds::fromArray([ActivityId::fromUnprefixed(4)])));

        $this->assertSame(
            "Importing activities...\n"
            .'  => [1/1] Updated activity: "Night Ride3 - 10-10-2023"',
            (string) $output,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test"}']
        );

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->strava = $this->getContainer()->get(Strava::class);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedImportSettings(array $overrides): void
    {
        $this->getContainer()->get(SettingsRepository::class)->saveGroup(SettingsGroup::IMPORT, [
            'numberOfNewActivitiesToProcessPerImport' => 250,
            'sportTypesToImport' => [],
            'activityVisibilitiesToImport' => [],
            'skipActivitiesRecordedBefore' => null,
            'activitiesToSkipDuringImport' => ['skip'],
            'optInToSegmentDetailImport' => true,
            'webhooks' => ['enabled' => true, 'verifyToken' => 'ffc26d52-d3ff-4797-a2b7-780a593a3547'],
            ...$overrides,
        ]);
    }
}
