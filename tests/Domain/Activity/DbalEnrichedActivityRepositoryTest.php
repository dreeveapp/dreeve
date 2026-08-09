<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\EnrichedActivity;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class DbalEnrichedActivityRepositoryTest extends ContainerTestCase
{
    use ProvideTestData;

    private EnrichedActivityRepository $enrichedActivityRepository;

    public function testFind(): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('9756441741');
        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);

        $this->assertEquals($activityId, $enrichedActivity->getActivity()->getId());
        $this->assertNull($enrichedActivity->getNormalizedPower());
        $this->assertEquals(102, $enrichedActivity->getMaxCadence());
        $this->assertEquals('Retro Race Bike', $enrichedActivity->getGearName());
        $this->assertTrue($enrichedActivity->hasDetailedPowerData());
        $this->assertEquals(493, $enrichedActivity->getBestAveragePowerForTimeInterval(5)?->getPower());
        $this->assertNull($enrichedActivity->getBestAveragePowerForTimeInterval(1));
    }

    public function testFindHydratesTheSameActivityAsTheActivityRepository(): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('9756441741');

        $this->assertEquals(
            $this->getContainer()->get(ActivityRepository::class)->find($activityId),
            $this->enrichedActivityRepository->find($activityId)->getActivity(),
        );
    }

    public function testFindForAnActivityWithoutGearStreamsOrMetrics(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->build(),
            [],
        ));

        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);

        $this->assertEquals($activityId, $enrichedActivity->getActivity()->getId());
        $this->assertNull($enrichedActivity->getNormalizedPower());
        $this->assertNull($enrichedActivity->getMaxCadence());
        $this->assertNull($enrichedActivity->getGearName());
        $this->assertFalse($enrichedActivity->hasDetailedPowerData());
        $this->assertNull($enrichedActivity->getBestAveragePowerForTimeInterval(5));
    }

    public function testFindForAnActivityWhoseMetricsAreThereButEmpty(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->build(),
            [],
        ));
        // A metric row that decodes to nothing is not the same as no metric row at all.
        $activityStreamMetricRepository = $this->getContainer()->get(ActivityStreamMetricRepository::class);
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::NORMALIZED_POWER,
            data: [],
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::CADENCE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [],
        ));

        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);

        $this->assertNull($enrichedActivity->getNormalizedPower());
        $this->assertNull($enrichedActivity->getMaxCadence());
    }

    public function testFindForAnActivityPredatingTheAthleteWeightHistory(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStartDateTime(SerializableDateTime::fromString('2019-01-01'))
                ->build(),
            [],
        ));
        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [5 => 493],
        ));

        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);

        $this->assertFalse($enrichedActivity->hasDetailedPowerData());
        $this->assertNull($enrichedActivity->getBestAveragePowerForTimeInterval(5));
    }

    public function testFindAll(): void
    {
        $this->provideFullTestSet();

        $enrichedActivities = $this->enrichedActivityRepository->findAll();

        $this->assertEquals(
            array_map(
                fn (Activity $activity): string => (string) $activity->getId(),
                $this->getContainer()->get(ActivityRepository::class)->findAll()->toArray()
            ),
            array_map(
                fn (EnrichedActivity $enrichedActivity): string => (string) $enrichedActivity->getActivity()->getId(),
                $enrichedActivities
            ),
        );
    }

    public function testFindAllEnrichesEveryActivityTheSameWayFindDoes(): void
    {
        $this->provideFullTestSet();

        foreach ($this->enrichedActivityRepository->findAll() as $enrichedActivity) {
            $this->assertEquals(
                $this->enrichedActivityRepository->find($enrichedActivity->getActivity()->getId()),
                $enrichedActivity,
            );
        }
    }

    public function testFindForAnActivityThatDoesNotExist(): void
    {
        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessageIsOrContains('Activity "activity-1" not found');

        $this->enrichedActivityRepository->find(ActivityId::fromUnprefixed('1'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->enrichedActivityRepository = $this->getContainer()->get(EnrichedActivityRepository::class);
    }
}
