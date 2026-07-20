<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;

/**
 * Resolves free-form sport names found in activity files.
 */
final class SportTypeName
{
    /**
     * @var array<string, SportType>
     */
    private const array ALIASES = [
        'running' => SportType::RUN,
        'streetrunning' => SportType::RUN,
        'trackrunning' => SportType::RUN,
        'trailrunning' => SportType::TRAIL_RUN,
        'treadmill' => SportType::VIRTUAL_RUN,
        'treadmillrunning' => SportType::VIRTUAL_RUN,
        'indoorrunning' => SportType::VIRTUAL_RUN,
        'virtualrunning' => SportType::VIRTUAL_RUN,
        'biking' => SportType::RIDE,
        'cycling' => SportType::RIDE,
        'roadbiking' => SportType::RIDE,
        'roadcycling' => SportType::RIDE,
        'mountainbiking' => SportType::MOUNTAIN_BIKE_RIDE,
        'gravelbiking' => SportType::GRAVEL_RIDE,
        'gravelcycling' => SportType::GRAVEL_RIDE,
        'indoorcycling' => SportType::VIRTUAL_RIDE,
        'spinning' => SportType::VIRTUAL_RIDE,
        'virtualcycling' => SportType::VIRTUAL_RIDE,
        'ebiking' => SportType::E_BIKE_RIDE,
        'handcycling' => SportType::HAND_CYCLE,
        'walking' => SportType::WALK,
        'casualwalking' => SportType::WALK,
        'speedwalking' => SportType::WALK,
        'hiking' => SportType::HIKE,
        'swimming' => SportType::SWIM,
        'lapswimming' => SportType::SWIM,
        'poolswimming' => SportType::SWIM,
        'openwaterswimming' => SportType::SWIM,
        'rowing' => SportType::ROWING,
        'indoorrowing' => SportType::VIRTUAL_ROW,
        'standuppaddleboarding' => SportType::STAND_UP_PADDLING,
        'sup' => SportType::STAND_UP_PADDLING,
        'windsurfing' => SportType::WIND_SURF,
        'kitesurfing' => SportType::KITE_SURF,
        'sailing' => SportType::SAIL,
        'crosscountryskiing' => SportType::NORDIC_SKI,
        'alpineskiing' => SportType::ALPINE_SKI,
        'downhillskiing' => SportType::ALPINE_SKI,
        'resortskiingsnowboarding' => SportType::ALPINE_SKI,
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

    public static function tryResolve(string $name): ?SportType
    {
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $name));
        if ('' === $normalized) {
            return null;
        }

        if (isset(self::ALIASES[$normalized])) {
            return self::ALIASES[$normalized];
        }

        // Some exporters write our (Strava) sport type names, e.g. "TrailRun" or "Kayaking".
        foreach (SportType::cases() as $sportType) {
            if (strtolower($sportType->value) === $normalized) {
                return $sportType;
            }
        }

        return null;
    }
}
