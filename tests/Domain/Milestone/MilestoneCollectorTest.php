<?php

namespace App\Tests\Domain\Milestone;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffort;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Discoverer\ActivityCountMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\ActivityDistanceMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\ActivityElevationMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\ActivityMovingTimeMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\CumulativeDistanceMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\CumulativeElevationMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\CumulativeMovingTimeMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\EddingtonMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\FirstActivityOfSportTypeMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\GearDistanceMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\GearElevationMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\GearMovingTimeMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\PersonalBestMilestoneDiscoverer;
use App\Domain\Milestone\Discoverer\StreakMilestoneDiscoverer;
use App\Domain\Milestone\MilestoneCollector;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Velocity\KmPerHour;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class MilestoneCollectorTest extends ContainerTestCase
{
    use MatchesSnapshots;

    public function testDiscoverAllWithNoActivities(): void
    {
        $collector = $this->createCollector();
        $this->assertTrue($collector->discoverAll()->isEmpty());
    }

    public function testDiscoverAllAggregatesFromAllDiscoverers(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->insertActivity($i, sprintf('2024-01-%02d', $i), SportType::RIDE, 20.0, 100.0, 3600, 20.0);
        }

        $this->insertBestEffort(1, SportType::RIDE, 10000, 1800);

        $collector = $this->createCollector();
        $collector->discoverAll();
        $milestones = $collector->discoverAll();

        $this->assertFalse($milestones->isEmpty());

        $categories = array_values(array_unique(array_map(
            fn ($m) => $m->getCategory()->value,
            $milestones->toArray()
        )));

        $this->assertMatchesJsonSnapshot(Json::encode($categories));
    }

    public function testDiscoverAllSortsNewestFirst(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->insertActivity($i, sprintf('2024-01-%02d', $i), SportType::RIDE, 20.0, 100.0, 3600, 20.0);
        }

        $collector = $this->createCollector();
        $milestones = $collector->discoverAll()->toArray();

        $dates = array_map(fn ($m) => $m->getAchievedOn()->format('Y-m-d'), $milestones);

        for ($i = 0; $i < count($dates) - 1; ++$i) {
            $this->assertGreaterThanOrEqual(
                $dates[$i + 1],
                $dates[$i],
                sprintf('Milestone at index %d (%s) should be >= milestone at index %d (%s)', $i, $dates[$i], $i + 1, $dates[$i + 1]),
            );
        }
    }

    public function testSortingWithMultipleMilestonesOnSameDay(): void
    {
        $this->insertActivity(1, '2024-06-15', SportType::RIDE, 120.0, 600.0, 14400, 30.0);

        $collector = $this->createCollector();
        $milestones = $collector->discoverAll()->toArray();

        $this->assertGreaterThan(1, count($milestones));

        $dates = array_map(fn ($m) => $m->getAchievedOn()->format('Y-m-d'), $milestones);
        foreach ($dates as $date) {
            $this->assertEquals('2024-06-15', $date);
        }
    }

    public function testSortingWithGapsBetweenMilestones(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 5.0, 50.0, 1800, 10.0);
        $this->insertActivity(2, '2024-06-01', SportType::RUN, 5.0, 50.0, 1800, 10.0);
        $this->insertActivity(3, '2024-12-01', SportType::RIDE, 100.0, 500.0, 36000, 25.0);

        $collector = $this->createCollector();
        $milestones = $collector->discoverAll()->toArray();

        $dates = array_map(fn ($m) => $m->getAchievedOn()->format('Y-m-d'), $milestones);

        for ($i = 0; $i < count($dates) - 1; ++$i) {
            $this->assertGreaterThanOrEqual($dates[$i + 1], $dates[$i]);
        }
    }

    public function testSortingMixesDifferentCategoriesChronologically(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 50.0, 300.0, 7200, 25.0);
        $this->insertActivity(2, '2024-01-02', SportType::RUN, 10.0, 50.0, 3600, 10.0);
        for ($i = 3; $i <= 10; ++$i) {
            $this->insertActivity($i, sprintf('2024-01-%02d', $i), SportType::RIDE, 5.0, 20.0, 1800, 10.0);
        }

        $collector = $this->createCollector();
        $milestones = $collector->discoverAll()->toArray();

        $categories = array_unique(array_map(fn ($m) => $m->getCategory()->value, $milestones));
        $this->assertGreaterThan(1, count($categories));

        $dates = array_map(fn ($m) => $m->getAchievedOn()->format('Y-m-d H:i:s'), $milestones);
        for ($i = 0; $i < count($dates) - 1; ++$i) {
            $this->assertGreaterThanOrEqual($dates[$i + 1], $dates[$i]);
        }
    }

    public function testSortingWithActivitiesInsertedOutOfOrder(): void
    {
        $this->insertActivity(1, '2024-12-01', SportType::RIDE, 60.0, 400.0, 7200, 30.0);
        $this->insertActivity(2, '2024-06-01', SportType::RUN, 15.0, 100.0, 5400, 10.0);
        $this->insertActivity(3, '2024-01-01', SportType::RIDE, 80.0, 500.0, 10800, 25.0);

        $collector = $this->createCollector();
        $milestones = $collector->discoverAll()->toArray();

        $dates = array_map(fn ($m) => $m->getAchievedOn()->format('Y-m-d'), $milestones);
        for ($i = 0; $i < count($dates) - 1; ++$i) {
            $this->assertGreaterThanOrEqual($dates[$i + 1], $dates[$i]);
        }
    }

    private function insertActivity(
        int $id,
        string $date,
        SportType $sportType,
        float $distanceKm,
        float $elevationM,
        int $movingTimeInSeconds,
        float $avgSpeedKmh,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withSportType($sportType)
                ->withDistance(Kilometer::from($distanceKm))
                ->withElevation(Meter::from($elevationM))
                ->withMovingTimeInSeconds($movingTimeInSeconds)
                ->withAverageSpeed(KmPerHour::from($avgSpeedKmh))
                ->build(), []
        ));
    }

    private function insertBestEffort(int $activityId, SportType $sportType, int $distanceInMeter, int $timeInSeconds): void
    {
        $this->getContainer()->get(ActivityBestEffortRepository::class)->add(
            ActivityBestEffort::create(
                activityId: ActivityId::fromUnprefixed($activityId),
                distanceInMeter: Meter::from($distanceInMeter),
                sportType: $sportType,
                timeInSeconds: $timeInSeconds,
            )
        );
    }

    private function createCollector(): MilestoneCollector
    {
        $connection = $this->getConnection();
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $milestoneIdFactory = new IncrementingMilestoneIdFactory();

        return new MilestoneCollector([
            new ActivityCountMilestoneDiscoverer($connection, $milestoneIdFactory),
            new ActivityDistanceMilestoneDiscoverer($connection, $milestoneIdFactory),
            new ActivityElevationMilestoneDiscoverer($connection, $milestoneIdFactory),
            new ActivityMovingTimeMilestoneDiscoverer($connection, $milestoneIdFactory),
            new CumulativeDistanceMilestoneDiscoverer($connection, $settingsRepository, $milestoneIdFactory),
            new CumulativeElevationMilestoneDiscoverer($connection, $settingsRepository, $milestoneIdFactory),
            new CumulativeMovingTimeMilestoneDiscoverer($connection, $milestoneIdFactory),
            new EddingtonMilestoneDiscoverer(
                $this->getContainer()->get(EddingtonCalculator::class),
                $settingsRepository,
                $milestoneIdFactory,
            ),
            new FirstActivityOfSportTypeMilestoneDiscoverer($connection, $milestoneIdFactory),
            new GearDistanceMilestoneDiscoverer($connection, $settingsRepository, $milestoneIdFactory),
            new GearElevationMilestoneDiscoverer($connection, $settingsRepository, $milestoneIdFactory),
            new GearMovingTimeMilestoneDiscoverer($connection, $milestoneIdFactory),
            new PersonalBestMilestoneDiscoverer($connection, $milestoneIdFactory),
            new StreakMilestoneDiscoverer($connection, $milestoneIdFactory),
        ]);
    }
}
