<?php

namespace App\Tests\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\PowerOutput;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class StreamBasedActivityPowerRepositoryTest extends ContainerTestCase
{
    private ActivityPowerRepository $activityPowerRepository;

    public function testFindBestForSportTypesForAnActivityPredatingTheAthleteWeightHistory(): void
    {
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('1'),
            startDate: SerializableDateTime::fromString('2019-01-01 10:00:00'),
            bestAverages: [5 => 900, 60 => 500, 3600 => 250],
        );

        $powerOutputs = $this->activityPowerRepository->findBestForSportTypes(
            SportTypes::thatSupportPeakPowerOutputs(ActivityType::RIDE)
        );

        $this->assertEquals(
            [5, 60, 3600],
            $powerOutputs->map(fn (PowerOutput $powerOutput): int => $powerOutput->getTimeIntervalInSeconds()),
        );
        $this->assertEquals(
            [900, 500, 250],
            $powerOutputs->map(fn (PowerOutput $powerOutput): int => $powerOutput->getPower()),
        );
        $this->assertEquals(
            [null, null, null],
            $powerOutputs->map(fn (PowerOutput $powerOutput): ?float => $powerOutput->getRelativePower()),
        );
    }

    public function testFindBestForSportTypesMixesActivitiesWithAndWithoutAKnownWeight(): void
    {
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('1'),
            startDate: SerializableDateTime::fromString('2019-01-01 10:00:00'),
            bestAverages: [5 => 900, 60 => 500, 3600 => 100],
        );
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('2'),
            startDate: SerializableDateTime::fromString('2020-06-01 10:00:00'),
            bestAverages: [5 => 400, 60 => 300, 3600 => 272],
        );

        $powerOutputs = $this->activityPowerRepository->findBestForSportTypes(
            SportTypes::thatSupportPeakPowerOutputs(ActivityType::RIDE)
        );

        $this->assertEquals(
            [5, 60, 3600],
            $powerOutputs->map(fn (PowerOutput $powerOutput): int => $powerOutput->getTimeIntervalInSeconds()),
        );
        $this->assertEquals(
            [900, 500, 272],
            $powerOutputs->map(fn (PowerOutput $powerOutput): int => $powerOutput->getPower()),
        );
        $this->assertEquals(
            [null, null, 4.0],
            $powerOutputs->map(fn (PowerOutput $powerOutput): ?float => $powerOutput->getRelativePower()),
        );
    }

    public function testFindBestForSportTypesIgnoresOtherActivityTypes(): void
    {
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('1'),
            startDate: SerializableDateTime::fromString('2020-06-01 10:00:00'),
            bestAverages: [5 => 400],
            sportType: SportType::RUN,
        );

        $this->assertTrue($this->activityPowerRepository->findBestForSportTypes(
            SportTypes::thatSupportPeakPowerOutputs(ActivityType::RIDE)
        )->isEmpty());
    }

    public function testFindBestForSportTypesInDateRange(): void
    {
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('1'),
            startDate: SerializableDateTime::fromString('2020-06-01 10:00:00'),
            bestAverages: [5 => 900],
        );
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('2'),
            startDate: SerializableDateTime::fromString('2021-06-01 10:00:00'),
            bestAverages: [5 => 400],
        );

        $powerOutputs = $this->activityPowerRepository->findBestForSportTypesInDateRange(
            sportTypes: SportTypes::thatSupportPeakPowerOutputs(ActivityType::RIDE),
            dateRange: DateRange::fromDates(
                from: SerializableDateTime::fromString('2021-01-01 00:00:00'),
                till: SerializableDateTime::fromString('2021-12-31 00:00:00'),
            ),
        );

        $this->assertEquals(
            [400],
            $powerOutputs->map(fn (PowerOutput $powerOutput): int => $powerOutput->getPower()),
        );
    }

    public function testFindBestForSportTypesSkipsExcludedActivities(): void
    {
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('1'),
            startDate: SerializableDateTime::fromString('2020-06-01 10:00:00'),
            bestAverages: [5 => 900],
        );
        $this->addRideWithBestAverages(
            activityId: ActivityId::fromUnprefixed('2'),
            startDate: SerializableDateTime::fromString('2020-06-02 10:00:00'),
            bestAverages: [5 => 400],
        );
        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            SettingsGroup::METRICS->keyValueKey(),
            Value::fromString(Json::encode(['excludeActivitiesFromPeakPowerOutputs' => ['1']])),
        ));

        $powerOutputs = $this->activityPowerRepository->findBestForSportTypes(
            SportTypes::thatSupportPeakPowerOutputs(ActivityType::RIDE)
        );

        $this->assertEquals(
            [400],
            $powerOutputs->map(fn (PowerOutput $powerOutput): int => $powerOutput->getPower()),
        );
    }

    /**
     * @param array<int, int> $bestAverages
     */
    private function addRideWithBestAverages(
        ActivityId $activityId,
        SerializableDateTime $startDate,
        array $bestAverages,
        SportType $sportType = SportType::RIDE,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType($sportType)
                ->withStartDateTime($startDate)
                ->build(),
            [],
        ));
        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: $bestAverages,
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityPowerRepository = $this->getContainer()->get(ActivityPowerRepository::class);
    }
}
