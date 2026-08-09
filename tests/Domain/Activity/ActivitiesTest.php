<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypes;
use App\Domain\Activity\SportType\SportType;
use PHPUnit\Framework\TestCase;

class ActivitiesTest extends TestCase
{
    public function testGetFirstActivityStartDateItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new \RuntimeException('No activities found'));
        Activities::empty()->getFirstActivityStartDate();
    }

    public function testGroupByActivityType(): void
    {
        $ride = ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build();
        $run = ActivityBuilder::fromDefaults()->withSportType(SportType::RUN)->build();
        $mountainBikeRide = ActivityBuilder::fromDefaults()->withSportType(SportType::MOUNTAIN_BIKE_RIDE)->build();

        $grouped = Activities::fromArray([$ride, $run, $mountainBikeRide])->groupByActivityType(
            // Walk has no activities, and the order of the buckets follows the given activity types.
            ActivityTypes::fromArray([ActivityType::RUN, ActivityType::WALK, ActivityType::RIDE])
        );

        $this->assertEquals(['Run', 'Walk', 'Ride'], array_keys($grouped));
        $this->assertEquals([$run], $grouped['Run']->toArray());
        $this->assertEquals([], $grouped['Walk']->toArray());
        $this->assertEquals([$ride, $mountainBikeRide], $grouped['Ride']->toArray());
    }

    public function testGroupByActivityTypeItShouldNotDropUnseededActivityTypes(): void
    {
        $run = ActivityBuilder::fromDefaults()->withSportType(SportType::RUN)->build();

        $grouped = Activities::fromArray([$run])->groupByActivityType(ActivityTypes::fromArray([ActivityType::RIDE]));

        $this->assertEquals([$run], $grouped['Run']->toArray());
    }

    public function testGroupByActivityTypeWithoutAnyActivities(): void
    {
        $this->assertEquals(
            ['Ride' => Activities::empty()],
            Activities::empty()->groupByActivityType(ActivityTypes::fromArray([ActivityType::RIDE]))
        );
    }
}
