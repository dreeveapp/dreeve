<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\FileParser\SportTypeName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SportTypeNameTest extends TestCase
{
    #[DataProvider('provideSportNames')]
    public function testTryResolve(string $name, ?SportType $expectedSportType): void
    {
        $this->assertSame($expectedSportType, SportTypeName::tryResolve($name));
    }

    public static function provideSportNames(): array
    {
        return [
            // Official TCX schema values.
            'Running' => ['Running', SportType::RUN],
            'Biking' => ['Biking', SportType::RIDE],
            'Other' => ['Other', null],
            // Non-standard values seen in the wild, in any casing or formatting.
            'running lowercase' => ['running', SportType::RUN],
            'cycling' => ['Cycling', SportType::RIDE],
            'walking' => ['Walking', SportType::WALK],
            'hiking' => ['hiking', SportType::HIKE],
            'swimming' => ['Swimming', SportType::SWIM],
            'trail running with space' => ['Trail Running', SportType::TRAIL_RUN],
            'trail running snake cased' => ['trail_running', SportType::TRAIL_RUN],
            'treadmill running' => ['Treadmill Running', SportType::VIRTUAL_RUN],
            'road biking' => ['road_biking', SportType::RIDE],
            'mountain biking' => ['Mountain Biking', SportType::MOUNTAIN_BIKE_RIDE],
            'gravel cycling' => ['Gravel Cycling', SportType::GRAVEL_RIDE],
            'indoor cycling' => ['Indoor Cycling', SportType::VIRTUAL_RIDE],
            'e-biking' => ['E-Biking', SportType::E_BIKE_RIDE],
            'hand cycling' => ['Hand Cycling', SportType::HAND_CYCLE],
            'indoor rowing' => ['Indoor Rowing', SportType::VIRTUAL_ROW],
            'lap swimming' => ['lap_swimming', SportType::SWIM],
            'open water swimming' => ['open_water_swimming', SportType::SWIM],
            'stand up paddleboarding' => ['Stand Up Paddleboarding', SportType::STAND_UP_PADDLING],
            'windsurfing' => ['Windsurfing', SportType::WIND_SURF],
            'kitesurfing' => ['Kitesurfing', SportType::KITE_SURF],
            'sailing' => ['Sailing', SportType::SAIL],
            'cross country skiing' => ['Cross Country Skiing', SportType::NORDIC_SKI],
            'alpine skiing' => ['Alpine Skiing', SportType::ALPINE_SKI],
            'downhill skiing' => ['Downhill Skiing', SportType::ALPINE_SKI],
            'resort skiing snowboarding' => ['resort_skiing_snowboarding', SportType::ALPINE_SKI],
            'backcountry skiing' => ['Backcountry Skiing', SportType::BACK_COUNTRY_SKI],
            'snowboarding' => ['Snowboarding', SportType::SNOWBOARD],
            'snowshoeing' => ['Snowshoeing', SportType::SNOWSHOE],
            'ice skating' => ['Ice Skating', SportType::ICE_SKATE],
            'inline skating' => ['Inline Skating', SportType::INLINE_SKATE],
            'skateboarding' => ['Skateboarding', SportType::SKATEBOARD],
            'rock climbing' => ['Rock Climbing', SportType::ROCK_CLIMBING],
            'strength training' => ['Strength Training', SportType::WEIGHT_TRAINING],
            'weightlifting' => ['Weightlifting', SportType::WEIGHT_TRAINING],
            'stair climbing' => ['Stair Climbing', SportType::STAIR_STEPPER],
            'hiit' => ['HIIT', SportType::HIIT],
            'golfing' => ['Golfing', SportType::GOLF],
            'dancing' => ['Dancing', SportType::DANCE],
            'surfing' => ['Surfing', SportType::SURFING],
            // Values matching our own sport type names.
            'kayaking' => ['Kayaking', SportType::KAYAKING],
            'rowing' => ['Rowing', SportType::ROWING],
            'canoeing' => ['canoeing', SportType::CANOEING],
            'trail run enum value' => ['TrailRun', SportType::TRAIL_RUN],
            'virtual ride enum value' => ['virtualride', SportType::VIRTUAL_RIDE],
            'yoga' => ['Yoga', SportType::YOGA],
            'pilates' => ['Pilates', SportType::PILATES],
            'elliptical' => ['Elliptical', SportType::ELLIPTICAL],
            'golf' => ['Golf', SportType::GOLF],
            'tennis' => ['Tennis', SportType::TENNIS],
            // Unknown values cannot be resolved.
            'unknown' => ['SomethingElse', null],
            'empty' => ['', null],
        ];
    }
}
