<?php

namespace App\Tests\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Athlete\HeartRateZone\HeartRateZone;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class StreamBasedActivityHeartRateRepositoryTest extends ContainerTestCase
{
    use ProvideTestData;

    private ActivityHeartRateRepository $activityHeartRateRepository;

    public function testItMatchesCountingTheRawStreamSampleBySample(): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('9756441741');
        $activity = $this->getContainer()->get(ActivityRepository::class)->find($activityId);
        $heartRateStream = $this->getContainer()->get(ActivityStreamRepository::class)
            ->findOneByActivityAndStreamType($activityId, StreamType::HEART_RATE);

        $general = $this->getContainer()->get(SettingsRepository::class)->general();
        $athleteMaxHeartRate = $general->getAthlete()->getMaxHeartRate($activity->getStartDate());
        $zones = $general->getHeartRateZoneConfiguration()->getHeartRateZonesFor(
            sportType: $activity->getSportType(),
            on: $activity->getStartDate()
        );

        $expected = [];
        foreach ($zones->getZones() as $zone) {
            [$minHeartRate, $maxHeartRate] = $zone->getRangeInBpm($athleteMaxHeartRate);
            $expected[$zone->getName()] = count(array_filter(
                $heartRateStream->getData(),
                fn (int $heartRate): bool => $heartRate >= $minHeartRate && $heartRate <= $maxHeartRate
            ));
        }

        $this->assertEquals(
            TimeInHeartRateZones::create(
                timeInZoneOne: $expected[HeartRateZone::ONE],
                timeInZoneTwo: $expected[HeartRateZone::TWO],
                timeInZoneThree: $expected[HeartRateZone::THREE],
                timeInZoneFour: $expected[HeartRateZone::FOUR],
                timeInZoneFive: $expected[HeartRateZone::FIVE],
            ),
            $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity($activityId),
        );
    }

    public function testFindTotalTimeInSecondsInHeartRateZonesForActivity(): void
    {
        $this->provideFullTestSet();

        $timeInHeartRateZones = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity(
            ActivityId::fromUnprefixed('9830227182')
        );

        $this->assertEquals(0, $timeInHeartRateZones->getTimeInZoneOne());
        $this->assertEquals(0, $timeInHeartRateZones->getTimeInZoneTwo());
        $this->assertEquals(0, $timeInHeartRateZones->getTimeInZoneThree());
        $this->assertEquals(0, $timeInHeartRateZones->getTimeInZoneFour());
        $this->assertEquals(10, $timeInHeartRateZones->getTimeInZoneFive());
    }

    #[DataProvider('provideActivitiesWithoutUsableHeartRateData')]
    public function testFindTotalTimeInSecondsInHeartRateZonesForActivityWithoutHeartRateData(string $activityId): void
    {
        $this->provideFullTestSet();

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessageIsOrContains(sprintf('HeartRateZones for "activity-%s" not found', $activityId));

        $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity(
            ActivityId::fromUnprefixed($activityId)
        );
    }

    public static function provideActivitiesWithoutUsableHeartRateData(): \Generator
    {
        yield 'no heart rate stream at all' => ['9542782314'];
        yield 'a heart rate stream rejected as faulty' => ['8756441741'];
    }

    public function testFindTotalTimeInSecondsInHeartRateZonesForActivityThatDoesNotExist(): void
    {
        $this->expectException(EntityNotFound::class);

        $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity(
            ActivityId::fromUnprefixed('1')
        );
    }

    public function testAnotherActivityDoesNotChangeTheZonesOfThisOne(): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('9830227182');
        $before = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity($activityId);

        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('42'),
            heartRateDistribution: [180 => 600],
        );

        $this->assertEquals(
            $before,
            $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity($activityId),
        );
    }

    public function testFindTotalTimeInSecondsInHeartRateZonesSeesEveryActivity(): void
    {
        $this->provideFullTestSet();

        $before = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZones();

        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('42'),
            heartRateDistribution: [180 => 600],
        );

        $this->assertEquals(
            $before->getTotalTimeInSeconds() + 600,
            $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZones()->getTotalTimeInSeconds(),
        );
    }

    public function testFindTotalTimeInSecondsInHeartRateZonesPerActivityType(): void
    {
        $this->provideFullTestSet();

        $perActivityType = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesPerActivityType();

        $this->assertEqualsCanonicalizing(
            array_map(fn (ActivityType $activityType): string => $activityType->value, ActivityType::cases()),
            array_keys($perActivityType),
        );
        $this->assertContainsOnlyInstancesOf(TimeInHeartRateZones::class, $perActivityType);

        $this->assertEquals(
            $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZones()->getTotalTimeInSeconds(),
            array_sum(array_map(
                fn (TimeInHeartRateZones $timeInHeartRateZones): int => $timeInHeartRateZones->getTotalTimeInSeconds(),
                $perActivityType
            )),
        );
    }

    public function testFindTimeInHeartRateZonesForLast30DaysIgnoresOlderActivities(): void
    {
        $this->provideFullTestSet();

        $before = $this->activityHeartRateRepository->findTimeInHeartRateZonesForLast30Days()->getCurrent();

        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('42'),
            heartRateDistribution: [180 => 600],
            startDate: SerializableDateTime::fromString('2020-01-01 10:00:00'),
        );

        $this->assertEquals(
            $before,
            $this->activityHeartRateRepository->findTimeInHeartRateZonesForLast30Days()->getCurrent(),
        );

        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('43'),
            heartRateDistribution: [180 => 600],
            startDate: SerializableDateTime::fromString('2023-10-15 10:00:00'),
        );

        $this->assertEquals(
            $before->getTotalTimeInSeconds() + 600,
            $this->activityHeartRateRepository->findTimeInHeartRateZonesForLast30Days()->getCurrent()->getTotalTimeInSeconds(),
        );
    }

    public function testFindTimeInHeartRateZonesForLast30DaysAlsoReturnsTheWindowAsOfTheDayBefore(): void
    {
        $this->provideFullTestSet();

        $before = $this->activityHeartRateRepository->findTimeInHeartRateZonesForLast30Days();

        // The clock is paused at 2023-10-17 16:15:04, so the current window starts at 2023-09-17 16:15:04
        // while yesterday's window runs from 2023-09-16 16:15:04 up to and including 2023-10-16 16:15:04.
        // 100 bpm lands in zone one and 180 bpm in zone five, so the two windows differ in shape as well as size.
        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('42'),
            heartRateDistribution: [180 => 600],
            startDate: SerializableDateTime::fromString('2023-10-17 10:00:00'),
        );
        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('43'),
            heartRateDistribution: [180 => 300],
            startDate: SerializableDateTime::fromString('2023-10-10 10:00:00'),
        );
        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('44'),
            heartRateDistribution: [100 => 1200],
            startDate: SerializableDateTime::fromString('2023-09-17 10:00:00'),
        );
        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('45'),
            heartRateDistribution: [100 => 999],
            startDate: SerializableDateTime::fromString('2020-01-01 10:00:00'),
        );

        $rollingWindow = $this->activityHeartRateRepository->findTimeInHeartRateZonesForLast30Days();

        $this->assertEquals(
            $before->getCurrent()->getTotalTimeInSeconds() + 900,
            $rollingWindow->getCurrent()->getTotalTimeInSeconds(),
        );
        $this->assertEquals(
            $before->getAsOfPreviousDay()->getTotalTimeInSeconds() + 1500,
            $rollingWindow->getAsOfPreviousDay()->getTotalTimeInSeconds(),
        );
        $this->assertNotEquals(
            $rollingWindow->getCurrent(),
            $rollingWindow->getAsOfPreviousDay(),
        );
    }

    /**
     * @param array<int, int> $heartRateDistribution
     */
    private function addActivityWithHeartRateDistribution(
        ActivityId $activityId,
        array $heartRateDistribution,
        ?SerializableDateTime $startDate = null,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType(SportType::RIDE)
                ->withStartDateTime($startDate ?? SerializableDateTime::fromString('2023-10-15 10:00:00'))
                ->build(),
            [],
        ));
        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: $heartRateDistribution,
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityHeartRateRepository = $this->getContainer()->get(ActivityHeartRateRepository::class);
    }
}
