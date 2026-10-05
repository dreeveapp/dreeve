<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Tests\ContainerTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityTypeTest extends ContainerTestCase
{
    public function testGetSportTypes(): void
    {
        $actual = [];
        foreach (ActivityType::cases() as $activityType) {
            $actual[$activityType->value] = $activityType->getSportTypes()->toArray();
        }
        $this->assertEquals(
            [
                ActivityType::RIDE->value => [SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE, SportType::GRAVEL_RIDE, SportType::E_BIKE_RIDE, SportType::E_MOUNTAIN_BIKE_RIDE, SportType::VIRTUAL_RIDE, SportType::VELO_MOBILE],
                ActivityType::RUN->value => [SportType::RUN, SportType::TRAIL_RUN, SportType::VIRTUAL_RUN],
                ActivityType::WALK->value => [SportType::WALK, SportType::HIKE],
                ActivityType::WATER_SPORTS->value => [SportType::CANOEING, SportType::KAYAKING, SportType::KITE_SURF, SportType::ROWING, SportType::STAND_UP_PADDLING, SportType::SURFING, SportType::WAKEBOARDING, SportType::POOL_SWIM, SportType::OPEN_WATER_SWIM, SportType::WIND_SURF],
                ActivityType::WINTER_SPORTS->value => [SportType::BACK_COUNTRY_SKI, SportType::ALPINE_SKI, SportType::NORDIC_SKI, SportType::ICE_SKATE, SportType::SNOWBOARD, SportType::SNOWSHOE],
                ActivityType::SKATING->value => [SportType::SKATEBOARD, SportType::INLINE_SKATE, SportType::ROLLER_SKI],
                ActivityType::RACQUET_PADDLE_SPORTS->value => [SportType::BADMINTON, SportType::PICKLE_BALL, SportType::RACQUET_BALL, SportType::SQUASH, SportType::TABLE_TENNIS, SportType::TENNIS, SportType::PADEL],
                ActivityType::FITNESS->value => [SportType::CROSSFIT, SportType::WEIGHT_TRAINING, SportType::WORKOUT, SportType::STAIR_STEPPER, SportType::VIRTUAL_ROW, SportType::HIIT, SportType::ELLIPTICAL, SportType::DANCE],
                ActivityType::MIND_BODY_SPORTS->value => [SportType::PILATES, SportType::YOGA, SportType::PHYSICAL_THERAPY],
                ActivityType::OUTDOOR_SPORTS->value => [SportType::GOLF, SportType::ROCK_CLIMBING, SportType::SAIL],
                ActivityType::TEAM_SPORTS->value => [SportType::BASKETBALL, SportType::SOCCER, SportType::VOLLEYBALL, SportType::CRICKET],
                ActivityType::ADAPTIVE_INCLUSIVE_SPORTS->value => [SportType::HAND_CYCLE, SportType::WHEELCHAIR],
                ActivityType::OTHER->value => [],
            ],
            $actual,
        );
    }

    public function testGetColor(): void
    {
        $actual = [];
        foreach (ActivityType::cases() as $activityType) {
            $actual[] = $activityType->getColor();
        }
        $this->assertEquals(
            [
                'emerald-600',
                'orange-500',
                'yellow-300',
                'blue-600',
                'red-600',
                'gray-600',
                'gray-600',
                'gray-600',
                'gray-600',
                'gray-600',
                'gray-600',
                'gray-600',
                'gray-600',
            ],
            $actual,
        );
    }

    public function testGetTranslations(): void
    {
        $actual = [];
        foreach (ActivityType::cases() as $activityType) {
            $actual[] = $activityType->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            [
                'Cycling',
                'Running',
                'Walking',
                'Water Sports',
                'Winter Sports',
                'Skating',
                'Racquet & Paddle Sports',
                'Fitness',
                'Mind & Body Sports',
                'Outdoor Sports',
                'Team Sports',
                'Adaptive & Inclusive Sports',
                'Other',
            ],
            $actual,
        );
    }
}
