<?php

declare(strict_types=1);

namespace App\Domain\Activity\SportType;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Measurement\Velocity\Velocity;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SportType: string implements TranslatableInterface
{
    // Cycle.
    case RIDE = 'Ride';
    case MOUNTAIN_BIKE_RIDE = 'MountainBikeRide';
    case GRAVEL_RIDE = 'GravelRide';
    case E_BIKE_RIDE = 'EBikeRide';
    case E_MOUNTAIN_BIKE_RIDE = 'EMountainBikeRide';
    case VIRTUAL_RIDE = 'VirtualRide';
    case VELO_MOBILE = 'Velomobile';
    // Run.
    case RUN = 'Run';
    case TRAIL_RUN = 'TrailRun';
    case VIRTUAL_RUN = 'VirtualRun';
    // Walk
    case WALK = 'Walk';
    case HIKE = 'Hike';
    // Water sports.
    case CANOEING = 'Canoeing';
    case KAYAKING = 'Kayaking';
    case KITE_SURF = 'Kitesurf';
    case ROWING = 'Rowing';
    case STAND_UP_PADDLING = 'StandUpPaddling';
    case SURFING = 'Surfing';
    case SWIM = 'Swim';
    case WIND_SURF = 'Windsurf';
    // Winter sports.
    case BACK_COUNTRY_SKI = 'BackcountrySki';
    case ALPINE_SKI = 'AlpineSki';
    case NORDIC_SKI = 'NordicSki';
    case ICE_SKATE = 'IceSkate';
    case SNOWBOARD = 'Snowboard';
    case SNOWSHOE = 'Snowshoe';
    // Skating.
    case SKATEBOARD = 'Skateboard';
    case INLINE_SKATE = 'InlineSkate';
    case ROLLER_SKI = 'RollerSki';
    // Racquet & Paddle Sports.
    case BADMINTON = 'Badminton';
    case PICKLE_BALL = 'Pickleball';
    case RACQUET_BALL = 'Racquetball';
    case SQUASH = 'Squash';
    case TABLE_TENNIS = 'TableTennis';
    case TENNIS = 'Tennis';
    case PADEL = 'Padel';
    // Fitness.
    case CROSSFIT = 'Crossfit';
    case WEIGHT_TRAINING = 'WeightTraining';
    case WORKOUT = 'Workout';
    case STAIR_STEPPER = 'StairStepper';
    case VIRTUAL_ROW = 'VirtualRow';
    case HIIT = 'HighIntensityIntervalTraining';
    case ELLIPTICAL = 'Elliptical';
    case DANCE = 'Dance';
    // Mind & Body Sports.
    case PILATES = 'Pilates';
    case YOGA = 'Yoga';
    case PHYSICAL_THERAPY = 'PhysicalTherapy';
    // Outdoor Sports.
    case GOLF = 'Golf';
    case ROCK_CLIMBING = 'RockClimbing';
    case SAIL = 'Sail';
    // Team Sports.
    case BASKETBALL = 'Basketball';
    case SOCCER = 'Soccer';
    case VOLLEYBALL = 'Volleyball';
    case CRICKET = 'Cricket';
    // Adaptive & Inclusive Sports.
    case HAND_CYCLE = 'Handcycle';
    case WHEELCHAIR = 'Wheelchair';

    public function getVelocityDisplayPreference(): Velocity
    {
        if (ActivityType::RUN === self::getActivityType() || ActivityType::WALK === self::getActivityType()) {
            return SecPerKm::zero();
        }

        return match ($this) {
            self::SWIM => SecPer100Meter::zero(),
            default => KmPerHour::zero(),
        };
    }

    public function getTemplateName(): string
    {
        if (ActivityType::RUN === self::getActivityType() || ActivityType::WALK === self::getActivityType()) {
            return 'activity--sport-type--run';
        }

        return match ($this) {
            self::SWIM => 'activity--sport-type--swim',
            default => 'activity--sport-type--generic',
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::RIDE => $translator->trans('Rides', locale: $locale),
            self::MOUNTAIN_BIKE_RIDE => $translator->trans('Mountain Bike Rides', locale: $locale),
            self::GRAVEL_RIDE => $translator->trans('Gravel Rides', locale: $locale),
            self::E_BIKE_RIDE => $translator->trans('E-Bike Rides', locale: $locale),
            self::E_MOUNTAIN_BIKE_RIDE => $translator->trans('E-Mountain Bike Rides', locale: $locale),
            self::VIRTUAL_RIDE => $translator->trans('Virtual Rides', locale: $locale),
            self::VELO_MOBILE => $translator->trans('Velo Mobiles', locale: $locale),
            self::RUN => $translator->trans('Runs', locale: $locale),
            self::TRAIL_RUN => $translator->trans('Trail Runs', locale: $locale),
            self::VIRTUAL_RUN => $translator->trans('Virtual Runs', locale: $locale),
            self::WALK => $translator->trans('Walks', locale: $locale),
            self::HIKE => $translator->trans('Hikes', locale: $locale),
            self::CANOEING => $translator->trans('Canoeing', locale: $locale),
            self::KAYAKING => $translator->trans('Kayaking', locale: $locale),
            self::KITE_SURF => $translator->trans('Kite Surf', locale: $locale),
            self::ROWING => $translator->trans('Rowing', locale: $locale),
            self::STAND_UP_PADDLING => $translator->trans('Stand Up Paddling', locale: $locale),
            self::SURFING => $translator->trans('Surfing', locale: $locale),
            self::SWIM => $translator->trans('Swim', locale: $locale),
            self::WIND_SURF => $translator->trans('Wind Surf', locale: $locale),
            self::BACK_COUNTRY_SKI => $translator->trans('Back Country Ski', locale: $locale),
            self::ALPINE_SKI => $translator->trans('Alpine Ski', locale: $locale),
            self::NORDIC_SKI => $translator->trans('Nordic Ski', locale: $locale),
            self::ICE_SKATE => $translator->trans('Ice Skate', locale: $locale),
            self::SNOWBOARD => $translator->trans('Snowboard', locale: $locale),
            self::SNOWSHOE => $translator->trans('Snowshoe', locale: $locale),
            self::INLINE_SKATE => $translator->trans('Inline Skate', locale: $locale),
            self::ROLLER_SKI => $translator->trans('Roller Ski', locale: $locale),
            self::SKATEBOARD => $translator->trans('Skateboard', locale: $locale),
            self::BADMINTON => $translator->trans('Badminton', locale: $locale),
            self::CROSSFIT => $translator->trans('Crossfit', locale: $locale),
            self::ELLIPTICAL => $translator->trans('Elliptical', locale: $locale),
            self::GOLF => $translator->trans('Golf', locale: $locale),
            self::HAND_CYCLE => $translator->trans('Hand Cycle', locale: $locale),
            self::HIIT => $translator->trans('HIIT', locale: $locale),
            self::PICKLE_BALL => $translator->trans('Pickle Ball', locale: $locale),
            self::PILATES => $translator->trans('Pilates', locale: $locale),
            self::RACQUET_BALL => $translator->trans('Racquet Ball', locale: $locale),
            self::PADEL => $translator->trans('Padel', locale: $locale),
            self::PHYSICAL_THERAPY => $translator->trans('Physical Therapy', locale: $locale),
            self::ROCK_CLIMBING => $translator->trans('Rock Climbing', locale: $locale),
            self::VIRTUAL_ROW => $translator->trans('Virtual Row', locale: $locale),
            self::SAIL => $translator->trans('Sail', locale: $locale),
            self::SOCCER => $translator->trans('Soccer', locale: $locale),
            self::BASKETBALL => $translator->trans('Basketball', locale: $locale),
            self::VOLLEYBALL => $translator->trans('Volleyball', locale: $locale),
            self::CRICKET => $translator->trans('Cricket', locale: $locale),
            self::DANCE => $translator->trans('Dance', locale: $locale),
            self::SQUASH => $translator->trans('Squash', locale: $locale),
            self::STAIR_STEPPER => $translator->trans('Stair Stepper', locale: $locale),
            self::TABLE_TENNIS => $translator->trans('Table Tennis', locale: $locale),
            self::TENNIS => $translator->trans('Tennis', locale: $locale),
            self::WEIGHT_TRAINING => $translator->trans('Weight Training', locale: $locale),
            self::WHEELCHAIR => $translator->trans('Wheelchair', locale: $locale),
            self::WORKOUT => $translator->trans('Workout', locale: $locale),
            self::YOGA => $translator->trans('Yoga', locale: $locale),
        };
    }

    public function getActivityType(): ActivityType
    {
        return match ($this) {
            // RIDE.
            SportType::RIDE, SportType::MOUNTAIN_BIKE_RIDE,
            SportType::GRAVEL_RIDE, SportType::E_BIKE_RIDE,
            SportType::E_MOUNTAIN_BIKE_RIDE, SportType::VIRTUAL_RIDE,
            SportType::VELO_MOBILE => ActivityType::RIDE,
            // RUN.
            SportType::RUN, SportType::TRAIL_RUN, SportType::VIRTUAL_RUN => ActivityType::RUN,
            // WALK.
            SportType::WALK, SportType::HIKE => ActivityType::WALK,
            // WATER.
            SportType::CANOEING, SportType::KAYAKING, SportType::KITE_SURF,
            SportType::ROWING, SportType::STAND_UP_PADDLING,
            SportType::SURFING, SportType::SWIM, SportType::WIND_SURF => ActivityType::WATER_SPORTS,
            // WINTER.
            SportType::BACK_COUNTRY_SKI, SportType::ALPINE_SKI, SportType::NORDIC_SKI,
            SportType::ICE_SKATE, SportType::SNOWBOARD, SportType::SNOWSHOE => ActivityType::WINTER_SPORTS,
            // SKATING.
            SportType::SKATEBOARD, SportType::INLINE_SKATE, SportType::ROLLER_SKI => ActivityType::SKATING,
            // RACQUET_PADDLE_SPORTS.
            SportType::BADMINTON, SportType::PICKLE_BALL, SportType::RACQUET_BALL,
            SportType::SQUASH, SportType::TABLE_TENNIS, SportType::TENNIS, SportType::PADEL => ActivityType::RACQUET_PADDLE_SPORTS,
            // FITNESS.
            SportType::CROSSFIT, SportType::WEIGHT_TRAINING, SportType::WORKOUT, SportType::STAIR_STEPPER,
            SportType::VIRTUAL_ROW, SportType::HIIT, SportType::ELLIPTICAL, SportType::DANCE => ActivityType::FITNESS,
            // MIND_BODY_SPORTS.
            SportType::PILATES, SportType::YOGA, SportType::PHYSICAL_THERAPY => ActivityType::MIND_BODY_SPORTS,
            // OUTDOOR_SPORTS.
            SportType::GOLF, SportType::ROCK_CLIMBING, SportType::SAIL => ActivityType::OUTDOOR_SPORTS,
            // TEAM SPORTS.
            SportType::SOCCER, SportType::BASKETBALL, SportType::VOLLEYBALL, SportType::CRICKET => ActivityType::TEAM_SPORTS,
            // ADAPTIVE_INCLUSIVE_SPORTS
            SportType::HAND_CYCLE, SportType::WHEELCHAIR => ActivityType::ADAPTIVE_INCLUSIVE_SPORTS,
        };
    }

    public function supportsBestEffortsStats(): bool
    {
        return in_array($this, [
            self::RIDE,
            self::MOUNTAIN_BIKE_RIDE,
            self::GRAVEL_RIDE,
            self::VIRTUAL_RIDE,
            self::E_MOUNTAIN_BIKE_RIDE,
            self::RUN,
            self::TRAIL_RUN,
            self::VIRTUAL_RUN,
        ]);
    }

    public function supportsReverseGeocoding(): bool
    {
        return !in_array($this, [
            self::VIRTUAL_RIDE,
            self::VIRTUAL_RUN,
            self::VIRTUAL_ROW,
            self::BADMINTON,
            self::CROSSFIT,
            self::ELLIPTICAL,
            self::HIIT,
            self::PICKLE_BALL,
            self::PILATES,
            self::RACQUET_BALL,
            self::SQUASH,
            self::TABLE_TENNIS,
            self::WEIGHT_TRAINING,
            self::WORKOUT,
            self::SWIM,
            self::ICE_SKATE,
            self::YOGA,
            self::STAIR_STEPPER,
            self::BASKETBALL,
            self::VOLLEYBALL,
            self::DANCE,
            self::PADEL,
            self::PHYSICAL_THERAPY,
        ]);
    }

    public function supportsWeather(): bool
    {
        return !in_array($this, [
            self::VIRTUAL_RIDE,
            self::VIRTUAL_RUN,
            self::VIRTUAL_ROW,
            self::BADMINTON,
            self::CROSSFIT,
            self::ELLIPTICAL,
            self::HIIT,
            self::PICKLE_BALL,
            self::PILATES,
            self::RACQUET_BALL,
            self::SQUASH,
            self::TABLE_TENNIS,
            self::WEIGHT_TRAINING,
            self::WORKOUT,
            self::SWIM,
            self::ICE_SKATE,
            self::YOGA,
            self::BASKETBALL,
            self::VOLLEYBALL,
            self::DANCE,
            self::PADEL,
            self::PHYSICAL_THERAPY,
        ]);
    }

    public function isVirtualRide(): bool
    {
        return self::VIRTUAL_RIDE === $this;
    }
}
