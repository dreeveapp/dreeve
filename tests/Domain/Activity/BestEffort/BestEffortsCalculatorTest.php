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
    private ActivityRepository $activityRepository;
    private ActivityBestEffortRepository $activityBestEffortRepository;

    public function testCalculate(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 10:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1800)
                ->build()
        );
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2021-01-01 10:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1500)
                ->build()
        );
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-15 10:00:00'))
                ->withSportType(SportType::MOUNTAIN_BIKE_RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withSportType(SportType::MOUNTAIN_BIKE_RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(2000)
                ->build()
        );
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-16 10:00:00'))
                ->withSportType(SportType::RUN)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withSportType(SportType::RUN)
                ->withDistanceInMeter(Meter::from(5000))
                ->withTimeInSeconds(1200)
                ->build()
        );

        $bestEfforts = $this->bestEffortsCalculator->calculate();

        $this->assertEquals(
            1500,
            $bestEfforts->for(BestEffortPeriod::ALL_TIME, SportType::RIDE, Kilometer::from(10))?->getTimeInSeconds()
        );
        $this->assertEquals(
            1800,
            $bestEfforts->for(BestEffortPeriod::FOUR_WEEKS, SportType::RIDE, Kilometer::from(10))?->getTimeInSeconds()
        );
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
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2021-01-01 10:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(1800)
                ->build()
        );

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
            $this->activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) $i))
                    ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 10:00:00'))
                    ->withSportType(SportType::RIDE)
                    ->build(),
                []
            ));
            $this->activityBestEffortRepository->add(
                ActivityBestEffortBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) $i))
                    ->withSportType(SportType::RIDE)
                    ->withDistanceInMeter(Meter::from(10000))
                    ->withTimeInSeconds(1800 + $i)
                    ->build()
            );
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->bestEffortsCalculator = $this->getContainer()->get(BestEffortsCalculator::class);
        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->activityBestEffortRepository = $this->getContainer()->get(ActivityBestEffortRepository::class);
    }
}
