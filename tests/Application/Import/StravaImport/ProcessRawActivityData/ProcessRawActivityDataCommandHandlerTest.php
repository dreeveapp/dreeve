<?php

namespace App\Tests\Application\Import\StravaImport\ProcessRawActivityData;

use App\Application\Import\StravaImport\ProcessRawActivityData\ProcessRawActivityData;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Lap\ActivityLapBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class ProcessRawActivityDataCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $output = new SpyOutput();

        // Activities for laps.
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-laps-not-imported'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01'))
                ->build(),
            Json::decode('{"laps":[{"id":52014888132,"resource_state":2,"name":"Lap 1","activity":{"id":14630387617,"visibility":"everyone","resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":643,"moving_time":644,"start_date":"2025-05-29T10:15:42Z","start_date_local":"2025-05-29T12:15:42Z","distance":5010.93,"average_speed":7.78,"max_speed":19.74,"lap_index":1,"split":1,"start_index":0,"end_index":607,"total_elevation_gain":68.2,"average_cadence":87.8,"device_watts":true,"average_watts":175.6,"average_heartrate":162.2,"max_heartrate":171},{"id":52014888145,"resource_state":2,"name":"Lap 2","activity":{"id":14630387617,"visibility":"everyone","resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":6076,"moving_time":6077,"start_date":"2025-05-29T10:26:26Z","start_date_local":"2025-05-29T12:26:26Z","distance":45126.6,"average_speed":7.43,"max_speed":19.88,"lap_index":2,"split":2,"start_index":608,"end_index":6672,"total_elevation_gain":828.8,"average_cadence":87.6,"device_watts":true,"average_watts":179.5,"average_heartrate":164.6,"max_heartrate":176}]}'),
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-laps-but-already-imported'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-10'))
                ->build(),
            Json::decode('{"laps":[{"id":52014888132,"resource_state":2,"name":"Lap 1","activity":{"id":14630387617,"visibility":"everyone","resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":643,"moving_time":644,"start_date":"2025-05-29T10:15:42Z","start_date_local":"2025-05-29T12:15:42Z","distance":5010.93,"average_speed":7.78,"max_speed":19.74,"lap_index":1,"split":1,"start_index":0,"end_index":607,"total_elevation_gain":68.2,"average_cadence":87.8,"device_watts":true,"average_watts":175.6,"average_heartrate":162.2,"max_heartrate":171},{"id":52014888145,"resource_state":2,"name":"Lap 2","activity":{"id":14630387617,"visibility":"everyone","resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":6076,"moving_time":6077,"start_date":"2025-05-29T10:26:26Z","start_date_local":"2025-05-29T12:26:26Z","distance":45126.6,"average_speed":7.43,"max_speed":19.88,"lap_index":2,"split":2,"start_index":608,"end_index":6672,"total_elevation_gain":828.8,"average_cadence":87.6,"device_watts":true,"average_watts":179.5,"average_heartrate":164.6,"max_heartrate":176}]}'),
        ));
        $this->getContainer()->get(ActivityLapRepository::class)->add(
            ActivityLapBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-laps-but-already-imported'))
                ->withLapNumber(3)
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('without-laps'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-09'))
                ->build(),
            []
        ));

        // Activities for splits.
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-splits-not-imported'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01'))
                ->build(),
            Json::decode('{"prefer_perceived_exertion":null,"splits_metric":[{"distance":1000.7,"elapsed_time":1215,"elevation_difference":131.2,"moving_time":895,"split":1,"average_speed":1.12,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.1,"elapsed_time":1052,"elevation_difference":60.1,"moving_time":912,"split":2,"average_speed":1.1,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.4,"elapsed_time":1336,"elevation_difference":143.1,"moving_time":982,"split":3,"average_speed":1.02,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":996.9,"elapsed_time":1010,"elevation_difference":78.1,"moving_time":935,"split":4,"average_speed":1.07,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1003.1,"elapsed_time":1968,"elevation_difference":-95.2,"moving_time":924,"split":5,"average_speed":1.09,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":999.2,"elapsed_time":1034,"elevation_difference":-62.8,"moving_time":839,"split":6,"average_speed":1.19,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":998.4,"elapsed_time":3037,"elevation_difference":-71.2,"moving_time":941,"split":7,"average_speed":1.06,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.3,"elapsed_time":1127,"elevation_difference":8.9,"moving_time":884,"split":8,"average_speed":1.13,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000.6,"elapsed_time":1683,"elevation_difference":-5.4,"moving_time":1065,"split":9,"average_speed":0.94,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":998.8,"elapsed_time":1233,"elevation_difference":76.3,"moving_time":1116,"split":10,"average_speed":0.89,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.3,"elapsed_time":1164,"elevation_difference":69.8,"moving_time":1125,"split":11,"average_speed":0.89,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000.1,"elapsed_time":1974,"elevation_difference":78.7,"moving_time":965,"split":12,"average_speed":1.04,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":997.4,"elapsed_time":901,"elevation_difference":-85.5,"moving_time":794,"split":13,"average_speed":1.26,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":999.9,"elapsed_time":1102,"elevation_difference":-137,"moving_time":975,"split":14,"average_speed":1.03,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000.1,"elapsed_time":973,"elevation_difference":-68.3,"moving_time":728,"split":15,"average_speed":1.37,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000,"elapsed_time":1162,"elevation_difference":-113.2,"moving_time":945,"split":16,"average_speed":1.06,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":0,"elapsed_time":2,"elevation_difference":-0.2,"moving_time":2,"split":17,"average_speed":0,"average_grade_adjusted_speed":null,"pace_zone":0}],"splits_standard":[{"distance":1611.6,"elapsed_time":1759,"elevation_difference":166.2,"moving_time":1422,"split":1,"average_speed":1.13,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.2,"elapsed_time":2086,"elevation_difference":193.3,"moving_time":1567,"split":2,"average_speed":1.03,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.8,"elapsed_time":2543,"elevation_difference":-17.2,"moving_time":1523,"split":3,"average_speed":1.06,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.9,"elapsed_time":1712,"elevation_difference":-133.9,"moving_time":1371,"split":4,"average_speed":1.17,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1610.8,"elapsed_time":3719,"elevation_difference":-13.7,"moving_time":1469,"split":5,"average_speed":1.1,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.3,"elapsed_time":2421,"elevation_difference":28.7,"moving_time":1715,"split":6,"average_speed":0.94,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1609,"elapsed_time":1913,"elevation_difference":141.1,"moving_time":1828,"split":7,"average_speed":0.88,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1609.5,"elapsed_time":2473,"elevation_difference":-25.7,"moving_time":1389,"split":8,"average_speed":1.16,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1611.6,"elapsed_time":1694,"elevation_difference":-176.2,"moving_time":1432,"split":9,"average_speed":1.13,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1513.6,"elapsed_time":1653,"elevation_difference":-155.2,"moving_time":1311,"split":10,"average_speed":1.15,"average_grade_adjusted_speed":null,"pace_zone":0}]}')
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-splits-but-already-imported'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-10'))
                ->build(),
            Json::decode('{"prefer_perceived_exertion":null,"splits_metric":[{"distance":1000.7,"elapsed_time":1215,"elevation_difference":131.2,"moving_time":895,"split":1,"average_speed":1.12,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.1,"elapsed_time":1052,"elevation_difference":60.1,"moving_time":912,"split":2,"average_speed":1.1,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.4,"elapsed_time":1336,"elevation_difference":143.1,"moving_time":982,"split":3,"average_speed":1.02,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":996.9,"elapsed_time":1010,"elevation_difference":78.1,"moving_time":935,"split":4,"average_speed":1.07,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1003.1,"elapsed_time":1968,"elevation_difference":-95.2,"moving_time":924,"split":5,"average_speed":1.09,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":999.2,"elapsed_time":1034,"elevation_difference":-62.8,"moving_time":839,"split":6,"average_speed":1.19,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":998.4,"elapsed_time":3037,"elevation_difference":-71.2,"moving_time":941,"split":7,"average_speed":1.06,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.3,"elapsed_time":1127,"elevation_difference":8.9,"moving_time":884,"split":8,"average_speed":1.13,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000.6,"elapsed_time":1683,"elevation_difference":-5.4,"moving_time":1065,"split":9,"average_speed":0.94,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":998.8,"elapsed_time":1233,"elevation_difference":76.3,"moving_time":1116,"split":10,"average_speed":0.89,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1001.3,"elapsed_time":1164,"elevation_difference":69.8,"moving_time":1125,"split":11,"average_speed":0.89,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000.1,"elapsed_time":1974,"elevation_difference":78.7,"moving_time":965,"split":12,"average_speed":1.04,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":997.4,"elapsed_time":901,"elevation_difference":-85.5,"moving_time":794,"split":13,"average_speed":1.26,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":999.9,"elapsed_time":1102,"elevation_difference":-137,"moving_time":975,"split":14,"average_speed":1.03,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000.1,"elapsed_time":973,"elevation_difference":-68.3,"moving_time":728,"split":15,"average_speed":1.37,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1000,"elapsed_time":1162,"elevation_difference":-113.2,"moving_time":945,"split":16,"average_speed":1.06,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":0,"elapsed_time":2,"elevation_difference":-0.2,"moving_time":2,"split":17,"average_speed":0,"average_grade_adjusted_speed":null,"pace_zone":0}],"splits_standard":[{"distance":1611.6,"elapsed_time":1759,"elevation_difference":166.2,"moving_time":1422,"split":1,"average_speed":1.13,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.2,"elapsed_time":2086,"elevation_difference":193.3,"moving_time":1567,"split":2,"average_speed":1.03,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.8,"elapsed_time":2543,"elevation_difference":-17.2,"moving_time":1523,"split":3,"average_speed":1.06,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.9,"elapsed_time":1712,"elevation_difference":-133.9,"moving_time":1371,"split":4,"average_speed":1.17,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1610.8,"elapsed_time":3719,"elevation_difference":-13.7,"moving_time":1469,"split":5,"average_speed":1.1,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1608.3,"elapsed_time":2421,"elevation_difference":28.7,"moving_time":1715,"split":6,"average_speed":0.94,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1609,"elapsed_time":1913,"elevation_difference":141.1,"moving_time":1828,"split":7,"average_speed":0.88,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1609.5,"elapsed_time":2473,"elevation_difference":-25.7,"moving_time":1389,"split":8,"average_speed":1.16,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1611.6,"elapsed_time":1694,"elevation_difference":-176.2,"moving_time":1432,"split":9,"average_speed":1.13,"average_grade_adjusted_speed":null,"pace_zone":0},{"distance":1513.6,"elapsed_time":1653,"elevation_difference":-155.2,"moving_time":1311,"split":10,"average_speed":1.15,"average_grade_adjusted_speed":null,"pace_zone":0}]}')
        ));
        $this->getContainer()->get(ActivitySplitRepository::class)->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-splits-but-already-imported'))
                ->withUnitSystem(UnitSystem::IMPERIAL)
                ->withSplitNumber(3)
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('without-splits'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-09'))
                ->build(),
            []
        ));

        // Activities for segments.
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withName('Test activity 1')
                ->withDeviceName('Zwift')
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01'))
                ->build(),
            [
                'segment_efforts' => [
                    [
                        'id' => '1',
                        'start_date_local' => '2023-07-29T09:34:03Z',
                        'name' => 'Segment Effort One',
                        'elapsed_time' => 300,
                        'segment' => [
                            'id' => '1',
                            'name' => 'Segment One',
                            'maximum_grade' => 5.3,
                            'distance' => 1000,
                            'country' => 'BE',
                        ],
                    ],
                ],
            ],
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withName('Test activity 2')
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01'))
                ->build(),
            [
                'segment_efforts' => [
                    [
                        'id' => '2',
                        'start_date_local' => '2023-07-29T09:34:03Z',
                        'name' => 'Segment Effort Two',
                        'segment' => [
                            'id' => '1',
                            'name' => 'Segment One',
                            'maximum_grade' => 5.3,
                            'distance' => 1000,
                        ],
                    ],
                ],
            ],
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(3))
                ->withName('Test activity 3')
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withName('Test activity 4')
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01'))
                ->build(),
            [
                'segment_efforts' => [
                    [
                        'id' => '3',
                        'start_date_local' => '2023-07-29T09:34:03Z',
                        'name' => 'Segment Effort Two',
                        'segment' => [
                            'id' => '2',
                            'name' => 'Segment Two',
                            'maximum_grade' => 5.3,
                            'distance' => 1000,
                            'starred' => true,
                        ],
                    ],
                ],
            ],
        ));
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(2))
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed(9542782314))
                ->withElapsedTimeInSeconds(9.3)
                ->withAverageWatts(200)
                ->withDistance(Kilometer::from(0.1))
                ->build()
        );
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(3))
                ->withSegmentId(SegmentId::fromUnprefixed('2'))
                ->withActivityId(ActivityId::fromUnprefixed(9542782314))
                ->withElapsedTimeInSeconds(9.3)
                ->withAverageWatts(200)
                ->withDistance(Kilometer::from(0.1))
                ->build()
        );
        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('2'))
                ->withIsFavourite(false)
                ->build()
        );

        $this->commandBus->dispatch(new ProcessRawActivityData($output));
        $this->assertMatchesTextSnapshot($output);

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM Segment')->fetchAllAssociative()
        );
        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM SegmentEffort')->fetchAllAssociative()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
