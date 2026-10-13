<?php

namespace App\Tests\Domain\Activity\Stream\Metric;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetrics;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\Metric\ActivityStreamValueDistributionCalculator;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class RecalculateStreamValueDistributionListenerTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;
    private ActivityStreamMetricRepository $activityStreamMetricRepository;

    public function testItRecalculatesWhenTheSportTypeChanges(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RIDE)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::VELOCITY)
                ->withData([3.0, 3.0, 4.0])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamValueDistributionCalculator::class)->calculateFor($activity->getId(), UnitSystem::METRIC);
        $bestAverages = ActivityStreamMetric::create(
            activityId: $activity->getId(),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [100],
        );
        $this->activityStreamMetricRepository->add($bestAverages);

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RUN),
            [],
        ));

        $distributions = [];
        foreach ($this->activityStreamMetricRepository->findByActivityIdAndMetricType($activity->getId(), ActivityStreamMetricType::VALUE_DISTRIBUTION) as $metric) {
            $distributions[$metric->getStreamType()->value] = $metric->getData();
        }
        $this->assertEquals(
            [
                StreamType::WATTS->value => [],
                StreamType::HEART_RATE->value => [],
                StreamType::VELOCITY->value => [250 => 1, 333 => 2],
                StreamType::CADENCE->value => [],
            ],
            $distributions
        );
        $this->assertEquals(
            ActivityStreamMetrics::fromArray([$bestAverages]),
            $this->activityStreamMetricRepository->findByActivityIdAndMetricType($activity->getId(), ActivityStreamMetricType::BEST_AVERAGES)
        );
    }

    public function testItLeavesTheCalculationToTheImportWhenNothingWasCalculatedYet(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RIDE)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RUN),
            [],
        ));

        $this->assertEquals(
            ActivityStreamMetrics::fromArray([]),
            $this->activityStreamMetricRepository->findByActivityId($activity->getId())
        );
    }

    public function testItLeavesDistributionsAloneWhenTheSportTypeDidNotChange(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RIDE)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $distribution = ActivityStreamMetric::create(
            activityId: $activity->getId(),
            streamType: StreamType::VELOCITY,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [11 => 2],
        );
        $this->activityStreamMetricRepository->add($distribution);

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withName(ActivityName::fromString('Renamed')),
            [],
        ));

        $this->assertEquals(
            ActivityStreamMetrics::fromArray([$distribution]),
            $this->activityStreamMetricRepository->findByActivityId($activity->getId())
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $this->activityStreamMetricRepository = $this->getContainer()->get(ActivityStreamMetricRepository::class);
    }
}
