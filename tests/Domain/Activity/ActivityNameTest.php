<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityName;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityNameTest extends TestCase
{
    #[DataProvider(methodName: 'provideActivityTitles')]
    public function testFrom(string $expectedTitle, string $dateTime, SportType $sportType): void
    {
        $this->assertEquals(
            $expectedTitle,
            (string) ActivityName::from(
                date: SerializableDateTime::fromString($dateTime),
                sportType: $sportType,
            ),
        );
    }

    public static function provideActivityTitles(): array
    {
        return [
            // Morning (5:00 - 11:59).
            'morning ride at 5:00' => ['Morning Ride', '2026-06-09 05:00:00', SportType::RIDE],
            'morning run at 6:30' => ['Morning Run', '2026-06-09 06:30:00', SportType::RUN],
            'morning walk at 8:00' => ['Morning Walk', '2026-06-09 08:00:00', SportType::WALK],
            'morning swim at 7:00' => ['Morning Swim', '2026-06-09 07:00:00', SportType::POOL_SWIM],
            'morning hike at 9:00' => ['Morning Hike', '2026-06-09 09:00:00', SportType::HIKE],
            'morning gravel ride at 10:00' => ['Morning Gravel Ride', '2026-06-09 10:00:00', SportType::GRAVEL_RIDE],
            'morning mountain bike ride at 11:59' => ['Morning Mountain Bike Ride', '2026-06-09 11:59:00', SportType::MOUNTAIN_BIKE_RIDE],
            'morning yoga at 7:30' => ['Morning Yoga', '2026-06-09 07:30:00', SportType::YOGA],
            'morning trail run at 6:00' => ['Morning Trail Run', '2026-06-09 06:00:00', SportType::TRAIL_RUN],
            // Afternoon (12:00 - 16:59).
            'afternoon ride at 12:00' => ['Afternoon Ride', '2026-06-09 12:00:00', SportType::RIDE],
            'afternoon run at 13:00' => ['Afternoon Run', '2026-06-09 13:00:00', SportType::RUN],
            'afternoon walk at 14:30' => ['Afternoon Walk', '2026-06-09 14:30:00', SportType::WALK],
            'afternoon swim at 15:00' => ['Afternoon Swim', '2026-06-09 15:00:00', SportType::POOL_SWIM],
            'afternoon weight training at 16:00' => ['Afternoon Weight Training', '2026-06-09 16:00:00', SportType::WEIGHT_TRAINING],
            'afternoon virtual ride at 16:59' => ['Afternoon Virtual Ride', '2026-06-09 16:59:00', SportType::VIRTUAL_RIDE],
            'afternoon soccer at 14:00' => ['Afternoon Soccer', '2026-06-09 14:00:00', SportType::SOCCER],
            // Evening (17:00 - 20:59).
            'evening ride at 17:00' => ['Evening Ride', '2026-06-09 17:00:00', SportType::RIDE],
            'evening run at 18:30' => ['Evening Run', '2026-06-09 18:30:00', SportType::RUN],
            'evening walk at 19:00' => ['Evening Walk', '2026-06-09 19:00:00', SportType::WALK],
            'evening crossfit at 18:00' => ['Evening Crossfit', '2026-06-09 18:00:00', SportType::CROSSFIT],
            'evening inline skate at 20:00' => ['Evening Inline Skate', '2026-06-09 20:00:00', SportType::INLINE_SKATE],
            'evening tennis at 20:59' => ['Evening Tennis', '2026-06-09 20:59:00', SportType::TENNIS],
            // Night (21:00 - 4:59).
            'night ride at 21:00' => ['Night Ride', '2026-06-09 21:00:00', SportType::RIDE],
            'night run at 22:00' => ['Night Run', '2026-06-09 22:00:00', SportType::RUN],
            'night walk at 23:59' => ['Night Walk', '2026-06-09 23:59:00', SportType::WALK],
            'night run at midnight' => ['Night Run', '2026-06-09 00:00:00', SportType::RUN],
            'night ride at 3:00' => ['Night Ride', '2026-06-09 03:00:00', SportType::RIDE],
            'night virtual run at 4:59' => ['Night Virtual Run', '2026-06-09 04:59:00', SportType::VIRTUAL_RUN],
            // Edge cases: multi-word sport types.
            'morning e-bike ride' => ['Morning E Bike Ride', '2026-06-09 08:00:00', SportType::E_BIKE_RIDE],
            'afternoon stand up paddling' => ['Afternoon Stand Up Paddling', '2026-06-09 14:00:00', SportType::STAND_UP_PADDLING],
            'evening rock climbing' => ['Evening Rock Climbing', '2026-06-09 19:00:00', SportType::ROCK_CLIMBING],
            'night back country ski' => ['Night Backcountry Ski', '2026-06-09 22:00:00', SportType::BACK_COUNTRY_SKI],
            'morning high intensity interval training' => ['Morning High Intensity Interval Training', '2026-06-09 06:00:00', SportType::HIIT],
        ];
    }
}
