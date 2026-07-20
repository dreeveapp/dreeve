<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Gpx;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\FileParser\Gpx\GpxSportType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GpxSportTypeTest extends TestCase
{
    #[DataProvider('provideSportMappings')]
    public function testResolve(string $gpxType, SportType $expectedSportType): void
    {
        $this->assertSame($expectedSportType, GpxSportType::resolve($gpxType));
    }

    public static function provideSportMappings(): array
    {
        return [
            // Strava numeric activity type ids.
            'strava ride' => ['1', SportType::RIDE],
            'strava hike' => ['4', SportType::HIKE],
            'strava run' => ['9', SportType::RUN],
            'strava walk' => ['10', SportType::WALK],
            'unknown numeric' => ['99', SportType::WORKOUT],
            // Plain sport names.
            'running' => ['running', SportType::RUN],
            'run' => ['run', SportType::RUN],
            'cycling' => ['cycling', SportType::RIDE],
            'biking' => ['biking', SportType::RIDE],
            'ride' => ['ride', SportType::RIDE],
            'walking' => ['walking', SportType::WALK],
            'hiking' => ['hiking', SportType::HIKE],
            'swimming' => ['swimming', SportType::SWIM],
            // Garmin Connect writes snake_cased activity types.
            'garmin trail running' => ['trail_running', SportType::TRAIL_RUN],
            'garmin treadmill running' => ['treadmill_running', SportType::VIRTUAL_RUN],
            'garmin road biking' => ['road_biking', SportType::RIDE],
            'garmin mountain biking' => ['mountain_biking', SportType::MOUNTAIN_BIKE_RIDE],
            'garmin gravel cycling' => ['gravel_cycling', SportType::GRAVEL_RIDE],
            'garmin indoor cycling' => ['indoor_cycling', SportType::VIRTUAL_RIDE],
            'garmin lap swimming' => ['lap_swimming', SportType::SWIM],
            'garmin open water swimming' => ['open_water_swimming', SportType::SWIM],
            'garmin casual walking' => ['casual_walking', SportType::WALK],
            'garmin indoor rowing' => ['indoor_rowing', SportType::VIRTUAL_ROW],
            'garmin cross country skiing' => ['cross_country_skiing', SportType::NORDIC_SKI],
            'garmin resort skiing snowboarding' => ['resort_skiing_snowboarding', SportType::ALPINE_SKI],
            'garmin backcountry skiing' => ['backcountry_skiing', SportType::BACK_COUNTRY_SKI],
            'garmin strength training' => ['strength_training', SportType::WEIGHT_TRAINING],
            'garmin stand up paddleboarding' => ['stand_up_paddleboarding', SportType::STAND_UP_PADDLING],
            // Values matching our own sport type names, in any casing.
            'kayaking' => ['Kayaking', SportType::KAYAKING],
            'rowing' => ['rowing', SportType::ROWING],
            'trail run enum value' => ['TrailRun', SportType::TRAIL_RUN],
            'virtual ride enum value' => ['virtualride', SportType::VIRTUAL_RIDE],
            'e-bike ride enum value' => ['EBikeRide', SportType::E_BIKE_RIDE],
            // Unknown values fall back to a generic workout.
            'unknown' => ['flying', SportType::WORKOUT],
            'empty' => ['', SportType::WORKOUT],
        ];
    }
}
