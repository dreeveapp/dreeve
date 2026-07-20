<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Tcx;

use App\Domain\Activity\SportType\SportType;

final class TcxSportType
{
    /**
     * @var array<string, SportType>
     */
    private const array ALIASES = [
        'running' => SportType::RUN,
        'biking' => SportType::RIDE,
        'cycling' => SportType::RIDE,
        'walking' => SportType::WALK,
        'hiking' => SportType::HIKE,
        'swimming' => SportType::SWIM,
        'trailrunning' => SportType::TRAIL_RUN,
        'treadmillrunning' => SportType::VIRTUAL_RUN,
        'indoorrunning' => SportType::VIRTUAL_RUN,
        'mountainbiking' => SportType::MOUNTAIN_BIKE_RIDE,
        'gravelbiking' => SportType::GRAVEL_RIDE,
        'gravelcycling' => SportType::GRAVEL_RIDE,
        'indoorcycling' => SportType::VIRTUAL_RIDE,
        'ebiking' => SportType::E_BIKE_RIDE,
        'handcycling' => SportType::HAND_CYCLE,
        'indoorrowing' => SportType::VIRTUAL_ROW,
        'standuppaddleboarding' => SportType::STAND_UP_PADDLING,
        'sup' => SportType::STAND_UP_PADDLING,
        'windsurfing' => SportType::WIND_SURF,
        'kitesurfing' => SportType::KITE_SURF,
        'sailing' => SportType::SAIL,
        'crosscountryskiing' => SportType::NORDIC_SKI,
        'alpineskiing' => SportType::ALPINE_SKI,
        'downhillskiing' => SportType::ALPINE_SKI,
        'backcountryskiing' => SportType::BACK_COUNTRY_SKI,
        'snowboarding' => SportType::SNOWBOARD,
        'snowshoeing' => SportType::SNOWSHOE,
        'iceskating' => SportType::ICE_SKATE,
        'inlineskating' => SportType::INLINE_SKATE,
        'skateboarding' => SportType::SKATEBOARD,
        'rockclimbing' => SportType::ROCK_CLIMBING,
        'climbing' => SportType::ROCK_CLIMBING,
        'strengthtraining' => SportType::WEIGHT_TRAINING,
        'weightlifting' => SportType::WEIGHT_TRAINING,
        'stairclimbing' => SportType::STAIR_STEPPER,
        'hiit' => SportType::HIIT,
        'golfing' => SportType::GOLF,
        'dancing' => SportType::DANCE,
        'surfing' => SportType::SURFING,
    ];

    public static function resolve(string $tcxSport): SportType
    {
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $tcxSport));

        if (isset(self::ALIASES[$normalized])) {
            return self::ALIASES[$normalized];
        }

        // Some exporters write our (Strava) sport type names, e.g. "TrailRun" or "Kayaking".
        foreach (SportType::cases() as $sportType) {
            if (strtolower($sportType->value) === $normalized) {
                return $sportType;
            }
        }

        return SportType::WORKOUT;
    }
}
