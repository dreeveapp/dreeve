<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class BestEffortsCalculatorTest extends ContainerTestCase
{
    private BestEffortsCalculator $bestEffortsCalculator;

    public function testCalculate(): void
    {
        // The clock is paused on 2023-10-17, so this one falls within every period.
        $this->addActivity('1', '2023-10-10 10:00:00', SportType::RIDE);
        $this->addBestEffort('1', SportType::RIDE, 10000, 1800);
        // Faster, but only within all time.
        $this->addActivity('2', '2021-01-01 10:00:00', SportType::RIDE);
        $this->addBestEffort('2', SportType::RIDE, 10000, 1500);
        // A second sport type for the same activity type.
        $this->addActivity('3', '2023-10-15 10:00:00', SportType::MOUNTAIN_BIKE_RIDE);
        $this->addBestEffort('3', SportType::MOUNTAIN_BIKE_RIDE, 10000, 2000);
        // A second activity type.
        $this->addActivity('4', '2023-10-16 10:00:00', SportType::RUN);
        $this->addBestEffort('4', SportType::RUN, 5000, 1200);

        $bestEfforts = $this->bestEffortsCalculator->calculate();

        $this->assertEquals(
            1500,
            $bestEfforts->for(BestEffortPeriod::ALL_TIME, SportType::RIDE, Kilometer::from(10))?->getTimeInSeconds()
        );
        $this->assertEquals(
            1800,
            $bestEfforts->for(BestEffortPeriod::FOUR_WEEKS, SportType::RIDE, Kilometer::from(10))?->getTimeInSeconds()
        );
        // A year before 2023-10-17 is 2022-10-17, so the 2021 activity is out of range here as well.
        $this->assertEquals(
            1800,
            $bestEfforts->for(BestEffortPeriod::YEAR, SportType::RIDE, Kilometer::from(10))?->getTimeInSeconds()
        );
        $this->assertEquals(
            2000,
            $bestEfforts->for(BestEffortPeriod::ALL_TIME, SportType::MOUNTAIN_BIKE_RIDE, Kilometer::from(10))?->getTimeInSeconds()
        );
        $this->assertNull($bestEfforts->for(BestEffortPeriod::ALL_TIME, SportType::RIDE, Kilometer::from(20)));
        $this->assertNull($bestEfforts->for(BestEffortPeriod::ALL_TIME, SportType::GRAVEL_RIDE, Kilometer::from(10)));

        $this->assertEquals(BestEffortPeriod::cases(), $bestEfforts->getPeriods());

        $this->assertEquals(
            [ActivityType::RIDE, ActivityType::RUN],
            $bestEfforts->getActivityTypes()->toArray()
        );

        $this->assertEquals(
            [SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE],
            $bestEfforts->getSportTypesFor(BestEffortPeriod::ALL_TIME, ActivityType::RIDE)->toArray()
        );
        $this->assertEquals(
            [SportType::RUN],
            $bestEfforts->getSportTypesFor(BestEffortPeriod::ALL_TIME, ActivityType::RUN)->toArray()
        );
        $this->assertTrue(
            $bestEfforts->getSportTypesFor(BestEffortPeriod::ALL_TIME, ActivityType::WALK)->isEmpty()
        );

        // The history is ranked over all time, regardless of the period.
        $this->assertEquals(
            1500,
            $bestEfforts->historyFor(SportType::RIDE, Kilometer::from(10), 0)?->getTimeInSeconds()
        );
        $this->assertEquals(
            1800,
            $bestEfforts->historyFor(SportType::RIDE, Kilometer::from(10), 1)?->getTimeInSeconds()
        );
        $this->assertNull($bestEfforts->historyFor(SportType::RIDE, Kilometer::from(10), 2));
    }

    public function testCalculateWithoutRecentActivities(): void
    {
        $this->addActivity('1', '2021-01-01 10:00:00', SportType::RIDE);
        $this->addBestEffort('1', SportType::RIDE, 10000, 1800);

        $bestEfforts = $this->bestEffortsCalculator->calculate();

        $this->assertEquals([BestEffortPeriod::ALL_TIME], $bestEfforts->getPeriods());
        $this->assertEquals([ActivityType::RIDE], $bestEfforts->getActivityTypes()->toArray());
        $this->assertNull($bestEfforts->for(BestEffortPeriod::FOUR_WEEKS, SportType::RIDE, Kilometer::from(10)));
        $this->assertTrue(
            $bestEfforts->getSportTypesFor(BestEffortPeriod::FOUR_WEEKS, ActivityType::RIDE)->isEmpty()
        );
    }

    public function testCalculateItShouldKeepTheTenFastestEfforts(): void
    {
        for ($i = 1; $i <= 12; ++$i) {
            $this->addActivity((string) $i, '2023-10-10 10:00:00', SportType::RIDE);
            $this->addBestEffort((string) $i, SportType::RIDE, 10000, 1800 + $i);
        }

        $bestEfforts = $this->bestEffortsCalculator->calculate();

        $this->assertEquals(
            1801,
            $bestEfforts->historyFor(SportType::RIDE, Kilometer::from(10), 0)?->getTimeInSeconds()
        );
        $this->assertEquals(
            1810,
            $bestEfforts->historyFor(SportType::RIDE, Kilometer::from(10), 9)?->getTimeInSeconds()
        );
        $this->assertNull($bestEfforts->historyFor(SportType::RIDE, Kilometer::from(10), 10));
    }

    public function testCalculateWithoutBestEfforts(): void
    {
        $bestEfforts = $this->bestEffortsCalculator->calculate();

        $this->assertEquals([], $bestEfforts->getPeriods());
        $this->assertTrue($bestEfforts->getActivityTypes()->isEmpty());
        $this->assertNull($bestEfforts->for(BestEffortPeriod::ALL_TIME, SportType::RIDE, Kilometer::from(10)));
        $this->assertNull($bestEfforts->historyFor(SportType::RIDE, Kilometer::from(10), 0));
    }

    private function addActivity(string $activityId, string $startDateTime, SportType $sportType): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->withSportType($sportType)
                ->build(),
            []
        ));
    }

    private function addBestEffort(string $activityId, SportType $sportType, int $distanceInMeter, int $timeInSeconds): void
    {
        $this->getContainer()->get(ActivityBestEffortRepository::class)->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withSportType($sportType)
                ->withDistanceInMeter(Meter::from($distanceInMeter))
                ->withTimeInSeconds($timeInSeconds)
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->bestEffortsCalculator = $this->getContainer()->get(BestEffortsCalculator::class);
    }
}
