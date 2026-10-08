<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateCustomSegmentEfforts;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\Signature\ActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\RouteCells;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Route\Signature\ActivityRouteSignatureBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\SpyOutput;

class CalculateCustomSegmentEffortsTest extends ContainerTestCase
{
    private const string FIXTURES = __DIR__.'/../../../../Domain/Segment/SegmentEffort/Matching/fixtures/';

    private CalculateCustomSegmentEfforts $calculateCustomSegmentEfforts;

    public function testProcess(): void
    {
        $output = new SpyOutput();
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        $segmentPolyline = EncodedPolyline::fromString(file_get_contents(self::FIXTURES.'segment-polyline.txt') ?: '');
        $segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('custom'))
            ->withName(Name::fromString('Custom segment'))
            ->withType(SegmentType::CUSTOM)
            ->withSportType(SportType::RIDE)
            ->withDistance(Kilometer::from(1))
            ->withPolyline($segmentPolyline)
            ->build());
        $segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('imported'))
            ->withType(SegmentType::IMPORTED)
            ->withSportType(SportType::RIDE)
            ->withPolyline($segmentPolyline)
            ->build());

        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        foreach ([
            ['exact-pass', SportType::RIDE, 'exact-pass'],
            ['two-laps', SportType::GRAVEL_RIDE, 'two-laps'],
            ['reversed', SportType::RIDE, 'reversed'],
            ['run', SportType::RUN, 'exact-pass'],
            ['far-away', SportType::RIDE, 'exact-pass'],
        ] as [$id, $sportType, $fixture]) {
            $activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($id))
                    ->withSportType($sportType)
                    ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 10:00:00'))
                    ->build(),
                []
            ));
            $streams = Json::decode(file_get_contents(self::FIXTURES.$fixture.'.json') ?: '');
            foreach ([
                StreamType::LAT_LNG->value => StreamType::LAT_LNG,
                StreamType::TIME->value => StreamType::TIME,
                StreamType::DISTANCE->value => StreamType::DISTANCE,
                StreamType::WATTS->value => StreamType::WATTS,
                StreamType::HEART_RATE->value => StreamType::HEART_RATE,
            ] as $key => $streamType) {
                $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($id))
                    ->withStreamType($streamType)
                    ->withData($streams[$key])
                    ->build());
            }
        }
        $this->getContainer()->get(ActivityRouteSignatureRepository::class)->add(ActivityRouteSignatureBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('far-away'))
            ->withCells(RouteCells::fromArray([1, 2, 3]))
            ->build());

        $this->calculateCustomSegmentEfforts->process($output);

        $this->assertEquals(
            [
                ['segmentId' => 'segment-custom', 'activityId' => 'activity-exact-pass', 'startDateTime' => '2025-01-01 10:00:37', 'name' => 'Custom segment', 'elapsedTimeInSeconds' => 125.0, 'distance' => 1000, 'averageWatts' => 209.5, 'averageHeartRate' => 145, 'maxHeartRate' => 149],
                ['segmentId' => 'segment-custom', 'activityId' => 'activity-two-laps', 'startDateTime' => '2025-01-01 10:00:37', 'name' => 'Custom segment', 'elapsedTimeInSeconds' => 125.0, 'distance' => 1000, 'averageWatts' => 209.5, 'averageHeartRate' => 145, 'maxHeartRate' => 149],
                ['segmentId' => 'segment-custom', 'activityId' => 'activity-two-laps', 'startDateTime' => '2025-01-01 10:08:08', 'name' => 'Custom segment', 'elapsedTimeInSeconds' => 125.0, 'distance' => 1000, 'averageWatts' => 209.5, 'averageHeartRate' => 144, 'maxHeartRate' => 149],
            ],
            $this->getConnection()->executeQuery(
                'SELECT segmentId, activityId, startDateTime, name, elapsedTimeInSeconds, distance, averageWatts, averageHeartRate, maxHeartRate
                 FROM SegmentEffort ORDER BY activityId, startDateTime'
            )->fetchAllAssociative(),
        );
        $this->assertEquals(
            ['activity-exact-pass', 'activity-far-away', 'activity-reversed', 'activity-two-laps'],
            $this->getConnection()->executeQuery(
                'SELECT activityId FROM SegmentActivityScan WHERE segmentId = "segment-custom" ORDER BY activityId'
            )->fetchFirstColumn(),
        );
        $this->assertEquals(
            0,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM SegmentActivityScan WHERE segmentId = "segment-imported"')->fetchOne(),
        );

        $this->calculateCustomSegmentEfforts->process($output);

        $this->assertEquals(3, $this->getConnection()->executeQuery('SELECT COUNT(*) FROM SegmentEffort')->fetchOne());
        $this->assertSame(
            "  => Scanned 0 activities for custom segment efforts (3 s)\n"
            ."  => Scanned 1 activities for custom segment efforts (3 s)\n"
            ."  => Scanned 2 activities for custom segment efforts (3 s)\n"
            ."  => Scanned 3 activities for custom segment efforts (3 s)\n"
            ."  => Scanned 3 activities for custom segment efforts (3 s)\n",
            (string) $output,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateCustomSegmentEfforts = $this->getContainer()->get(CalculateCustomSegmentEfforts::class);
    }
}
