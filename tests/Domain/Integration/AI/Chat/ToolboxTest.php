<?php

namespace App\Tests\Domain\Integration\AI\Chat;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Challenge\ChallengeId;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Integration\AI\Chat\Toolbox;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Lap\ActivityLapBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Challenge\ChallengeBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

class ToolboxTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideGearMaintenanceConfig;

    #[DataProvider(methodName: 'provideTools')]
    public function testTools(string $name, array $inputs): void
    {
        $this->assertMatchesJsonSnapshot($this->executeTool($name, $inputs));
    }

    #[DataProvider(methodName: 'provideToolErrors')]
    public function testItReturnsAToolError(string $name, array $inputs, string $message): void
    {
        $this->assertSame($message, $this->executeTool($name, $inputs));
    }

    public static function provideToolErrors(): iterable
    {
        yield 'get_activity_by_id for an unknown activity' => ['get_activity_by_id', ['activityId' => '1'], 'Activity "activity-1" does not exist.'];
        yield 'get_streaks with an unknown sport type' => ['get_streaks', ['sportType' => 'Ride,Flying'], '"Flying" is not a known sport type.'];
        yield 'get_training_volume with an invalid date' => ['get_training_volume', ['from' => '2025-13-01', 'to' => '2025-12-31'], '"from" must be a date in YYYY-MM-DD format.'];
        yield 'get_training_volume with a reversed date range' => ['get_training_volume', ['from' => '2025-02-01', 'to' => '2025-01-01'], '"from" must not be later than "to".'];
        yield 'search_activities with an unknown sport type' => ['search_activities', ['sportType' => 'Flying'], '"Flying" is not a known sport type.'];
        yield 'search_activities with an invalid date' => ['search_activities', ['to' => '2025-02-30'], '"to" must be a date in YYYY-MM-DD format.'];
        yield 'search_activities with a reversed date range' => ['search_activities', ['from' => '2025-02-01', 'to' => '2025-01-01'], '"from" must not be later than "to".'];
    }

    public function testItListsNoGearMaintenanceTasksWhenTheFeatureIsDisabled(): void
    {
        $this->importGearMaintenanceConfigFromArray(['enabled' => false]);

        $this->assertSame('{"maintenanceTasks":[]}', $this->executeTool('list_gear_maintenance_tasks', []));
    }

    public function testItRegistersAllTools(): void
    {
        $tools = $this->getContainer()->get(Toolbox::class)->getTools();
        usort($tools, static fn (Tool $a, Tool $b): int => $a->getName() <=> $b->getName());

        $this->assertCount(19, $tools);
        $this->assertMatchesJsonSnapshot(Json::encode(array_map(
            static fn (Tool $tool): array => [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ],
            $tools,
        )));
    }

    public static function provideTools(): iterable
    {
        yield 'GetActivity Tool' => ['get_activity_by_id', ['activityId' => '123456789']];
        yield 'GetActivity Tool for an activity not on Strava' => ['get_activity_by_id', ['activityId' => '987654321']];
        yield 'GetActivityLaps Tool' => ['get_activity_laps', ['activityId' => '123456789']];
        yield 'GetActivitySegmentEfforts Tool' => ['get_activity_segments_efforts', ['activityId' => '123456789']];
        yield 'GetActivityStreams Tool' => ['get_activity_streams', ['activityId' => '123456789']];
        yield 'GetActivitySplits Tool' => ['get_activity_splits', ['activityId' => '123456789']];
        yield 'GetAthleteDetails Tool' => ['get_athlete_details', []];
        yield 'GetChallenges Tool' => ['get_challenges', []];
        yield 'GetDefaultHeartRateZone Tool' => ['get_heart_rate_zones', []];
        yield 'GetGear Tool' => ['get_gear', []];
        yield 'GetFtpHistory Tool' => ['get_ftp_history', []];
        yield 'GetAllActivitiesSummary Tool' => ['get_activities_summary', []];
        yield 'GetAllActivitiesSummary Tool outside date range' => ['get_activities_summary', ['from' => '2024-01-01', 'to' => '2024-12-31']];
        yield 'GetMostRecentActivity Tool' => ['get_most_recent_activity', []];
        yield 'GetSegment Tool' => ['get_segment_by_id', ['segmentId' => '1']];
        yield 'GetSegment Tool for a custom segment' => ['get_segment_by_id', ['segmentId' => 'custom']];
        yield 'SearchActivities Tool' => ['search_activities', []];
        yield 'GetTrainingLoad Tool' => ['get_training_load', []];
        yield 'GetTrainingVolume Tool' => ['get_training_volume', ['from' => '2025-01-01', 'to' => '2025-01-31']];
        yield 'GetTrainingVolume Tool for a sport type' => ['get_training_volume', ['from' => '2025-01-01', 'to' => '2025-01-31', 'sportType' => 'Run']];
        yield 'GetStreaks Tool' => ['get_streaks', []];
        yield 'GetEddington Tool' => ['get_eddington', []];
        yield 'ListGearMaintenanceTasks Tool' => ['list_gear_maintenance_tasks', []];
        yield 'ListGearMaintenanceTasks Tool for a gear' => ['list_gear_maintenance_tasks', ['gearId' => 'retired']];
        yield 'SearchActivities Tool with filters' => ['search_activities', ['from' => '2025-01-01', 'to' => '2025-01-31', 'sportType' => 'Ride', 'hasGpx' => false, 'page' => 1, 'size' => 1]];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->importGearMaintenanceConfig();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('123456789'))
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 02:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('987654321'))
                ->withImportSource(ImportSource::FIT_FILE)
                ->withStartDateTime(SerializableDateTime::fromString('2020-01-01 02:00:00'))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityLapRepository::class)->add(ActivityLapBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('123456789'))
            ->build()
        );

        $this->getContainer()->get(ActivitySplitRepository::class)->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('123456789'))
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(3)
            ->build());

        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('123456789'))
                ->withStreamType(StreamType::WATTS)
                ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
                ->build()
        );

        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('123456789'))
                ->withStreamType(StreamType::CADENCE)
                ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
                ->build()
        );

        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('123456789'))
                ->withStreamType(StreamType::HEART_RATE)
                ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
                ->build()
        );

        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('123456789'))
                ->withStreamType(StreamType::VELOCITY)
                ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
                ->build()
        );

        $bestAveragesData = Json::decode('{"1":540,"5":493,"10":460,"15":442,"30":412,"45":373,"60":352,"120":293,"180":273,"240":282,"300":273,"390":262,"480":256,"720":246,"960":239,"1200":239,"1800":225,"2400":222,"3000":222,"3600":218}');
        $metricRepository = $this->getContainer()->get(ActivityStreamMetricRepository::class);
        foreach ([StreamType::WATTS, StreamType::CADENCE, StreamType::HEART_RATE, StreamType::VELOCITY] as $streamType) {
            $metricRepository->add(ActivityStreamMetric::create(
                activityId: ActivityId::fromUnprefixed('123456789'),
                streamType: $streamType,
                metricType: ActivityStreamMetricType::BEST_AVERAGES,
                data: $bestAveragesData,
            ));
        }

        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-06-20 09:04:58'))
                ->withDistanceInMeter(Meter::from(1450147))
                ->withName('Retro Race Bike')
                ->withIsRetired(false)
                ->build()
        );
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659862'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-06-20 09:04:58'))
                ->withDistanceInMeter(Meter::from(145014))
                ->withName('Zwift hub')
                ->withIsRetired(false)
                ->build()
        );

        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withName(Name::fromString('Segment One'))
                ->withDistance(Kilometer::from(0.1))
                ->withMaxGradient(5.3)
                ->withIsFavourite(true)
                ->withDeviceName('Polar')
                ->withSportType(SportType::RIDE)
                ->build()
        );
        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('custom'))
                ->withType(SegmentType::CUSTOM)
                ->build()
        );

        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('1'))
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed('123456789'))
                ->withElapsedTimeInSeconds(10.3)
                ->withAverageWatts(200)
                ->withDistance(Kilometer::from(0.1))
                ->build()
        );

        $this->getContainer()->get(ChallengeRepository::class)->add(
            ChallengeBuilder::fromDefaults()
                ->withChallengeId(ChallengeId::fromUnprefixed('4022'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-10-13 18:38:34'))
                ->withName('Castelli Keep It Up')
                ->withSlug('castelli-keep-it-up-2023')
                ->withLogoUrl('https://dgalywyr863hv.cloudfront.net/challenges/4022/4022-logo-100.png')
                ->withLocalLogoUrl('files/challenges/b7537b1e-69f7-11ee-a81e-0242a0cd5a47.png')
                ->build()
        );
    }

    /**
     * @param array<string, mixed> $inputs
     */
    private function executeTool(string $name, array $inputs): string
    {
        $result = $this->getContainer()->get(Toolbox::class)->execute(new ToolCall('call', $name, $inputs))->getResult();
        $this->assertIsString($result);

        return $result;
    }
}
