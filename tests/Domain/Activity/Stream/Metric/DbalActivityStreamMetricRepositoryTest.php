<?php

namespace App\Tests\Domain\Activity\Stream\Metric;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetrics;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\Metric\DbalActivityStreamMetricRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class DbalActivityStreamMetricRepositoryTest extends ContainerTestCase
{
    private ActivityStreamMetricRepository $activityStreamMetricRepository;
    private DbalActivityStreamRepository $activityStreamRepository;

    public function testAdd(): void
    {
        $metric = ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('1'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [100, 200, 300],
        );

        $this->activityStreamMetricRepository->add($metric);

        $found = $this->activityStreamMetricRepository->findByActivityIdAndMetricType(
            ActivityId::fromUnprefixed('1'),
            ActivityStreamMetricType::BEST_AVERAGES,
        );

        $this->assertEquals(ActivityStreamMetrics::fromArray([$metric]), $found);
    }

    public function testDeleteForActivity(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');

        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [100],
        ));
        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [150],
        ));

        $otherMetric = ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('2'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [200],
        );
        $this->activityStreamMetricRepository->add($otherMetric);

        $this->activityStreamMetricRepository->deleteForActivity($activityId);

        $this->assertEquals(
            ActivityStreamMetrics::fromArray([]),
            $this->activityStreamMetricRepository->findByActivityIdAndMetricType($activityId, ActivityStreamMetricType::BEST_AVERAGES),
        );
        $this->assertEquals(
            ActivityStreamMetrics::fromArray([$otherMetric]),
            $this->activityStreamMetricRepository->findByActivityIdAndMetricType(ActivityId::fromUnprefixed('2'), ActivityStreamMetricType::BEST_AVERAGES),
        );
    }

    public function testFindActivityIdsWithoutBestAverages(): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );
        // Activity 4 has a stream type that doesn't support best averages.
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withStreamType(StreamType::TIME)
                ->build()
        );

        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('1'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [100],
        ));

        $result = $this->activityStreamMetricRepository->findActivityIdsWithoutBestAverages();

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('2'),
                ActivityId::fromUnprefixed('3'),
            ]),
            $result,
        );
    }

    public function testFindActivityIdsWithoutBestAveragesWhenAllHaveMetrics(): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );

        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('1'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [100],
        ));

        $this->assertEquals(
            ActivityIds::fromArray([]),
            $this->activityStreamMetricRepository->findActivityIdsWithoutBestAverages(),
        );
    }

    public function testFindActivityIdsWithoutNormalizedPower(): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );
        // Activity 3 has heart rate, not watts - should not appear.
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withStreamType(StreamType::HEART_RATE)
                ->build()
        );

        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('1'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::NORMALIZED_POWER,
            data: [250],
        ));

        $result = $this->activityStreamMetricRepository->findActivityIdsWithoutNormalizedPower();

        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('2')]),
            $result,
        );
    }

    public function testFindActivityIdsWithoutNormalizedPowerWhenAllHaveMetrics(): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );

        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('1'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::NORMALIZED_POWER,
            data: [250],
        ));

        $this->assertEquals(
            ActivityIds::fromArray([]),
            $this->activityStreamMetricRepository->findActivityIdsWithoutNormalizedPower(),
        );
    }

    public function testFindActivityIdsWithoutDistributionValues(): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStreamType(StreamType::HEART_RATE)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withStreamType(StreamType::VELOCITY)
                ->build()
        );
        // Activity 4 has a stream type that doesn't support distribution.
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withStreamType(StreamType::ALTITUDE)
                ->build()
        );

        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('1'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [10, 20],
        ));

        $result = $this->activityStreamMetricRepository->findActivityIdsWithoutDistributionValues();

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('2'),
                ActivityId::fromUnprefixed('3'),
            ]),
            $result,
        );
    }

    public function testFindActivityIdsWithoutDistributionValuesWhenAllHaveMetrics(): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::WATTS)
                ->build()
        );

        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('1'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [10, 20],
        ));

        $this->assertEquals(
            ActivityIds::fromArray([]),
            $this->activityStreamMetricRepository->findActivityIdsWithoutDistributionValues(),
        );
    }

    public function testFindByActivityIdAndMetricType(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');

        $bestAveragesWatts = ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [100, 200],
        );
        $bestAveragesHeartRate = ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [150, 160],
        );
        $normalizedPower = ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::NORMALIZED_POWER,
            data: [250],
        );

        $this->activityStreamMetricRepository->add($bestAveragesWatts);
        $this->activityStreamMetricRepository->add($bestAveragesHeartRate);
        $this->activityStreamMetricRepository->add($normalizedPower);

        // Different activity, should not be included.
        $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('2'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [300],
        ));

        $this->assertEquals(
            ActivityStreamMetrics::fromArray([$bestAveragesWatts, $bestAveragesHeartRate]),
            $this->activityStreamMetricRepository->findByActivityIdAndMetricType($activityId, ActivityStreamMetricType::BEST_AVERAGES),
        );

        $this->assertEquals(
            ActivityStreamMetrics::fromArray([$normalizedPower]),
            $this->activityStreamMetricRepository->findByActivityIdAndMetricType($activityId, ActivityStreamMetricType::NORMALIZED_POWER),
        );
    }

    public function testFindByActivityIdAndMetricTypeReturnsEmptyWhenNoneFound(): void
    {
        $this->assertEquals(
            ActivityStreamMetrics::fromArray([]),
            $this->activityStreamMetricRepository->findByActivityIdAndMetricType(
                ActivityId::fromUnprefixed('999'),
                ActivityStreamMetricType::BEST_AVERAGES,
            ),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityStreamMetricRepository = new DbalActivityStreamMetricRepository(
            $this->getConnection(),
        );
        $this->activityStreamRepository = new DbalActivityStreamRepository(
            $this->getConnection(),
        );
    }
}
