<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Fit;

use App\Domain\Activity\SportType\SportType;

final class FitSportType
{
    private const int SPORT_RUNNING = 1;
    private const int SPORT_CYCLING = 2;
    private const int SPORT_FITNESS_EQUIPMENT = 4;
    private const int SPORT_SWIMMING = 5;
    private const int SPORT_BASKETBALL = 6;
    private const int SPORT_SOCCER = 7;
    private const int SPORT_TENNIS = 8;
    private const int SPORT_TRAINING = 10;
    private const int SPORT_WALKING = 11;
    private const int SPORT_CROSS_COUNTRY_SKIING = 12;
    private const int SPORT_ALPINE_SKIING = 13;
    private const int SPORT_SNOWBOARDING = 14;
    private const int SPORT_ROWING = 15;
    private const int SPORT_HIKING = 17;
    private const int SPORT_PADDLING = 19;
    private const int SPORT_E_BIKING = 21;
    private const int SPORT_GOLF = 25;
    private const int SPORT_INLINE_SKATING = 30;
    private const int SPORT_ROCK_CLIMBING = 31;
    private const int SPORT_SAILING = 32;
    private const int SPORT_ICE_SKATING = 33;
    private const int SPORT_SNOWSHOEING = 35;
    private const int SPORT_STAND_UP_PADDLEBOARDING = 37;
    private const int SPORT_SURFING = 38;
    private const int SPORT_KAYAKING = 41;
    private const int SPORT_WINDSURFING = 43;
    private const int SPORT_KITESURFING = 44;
    private const int SPORT_FLOOR_CLIMBING = 48;
    private const int SPORT_HIIT = 62;
    private const int SPORT_RACKET = 64;
    private const int SPORT_WHEELCHAIR_PUSH_WALK = 65;
    private const int SPORT_WHEELCHAIR_PUSH_RUN = 66;
    private const int SPORT_CRICKET = 71;
    private const int SPORT_VOLLEYBALL = 75;
    private const int SPORT_DANCE = 83;
    private const int SPORT_CANOEING = 88;

    private const int SUB_SPORT_TREADMILL = 1;
    private const int SUB_SPORT_TRAIL = 3;
    private const int SUB_SPORT_SPIN = 5;
    private const int SUB_SPORT_INDOOR_CYCLING = 6;
    private const int SUB_SPORT_MOUNTAIN = 8;
    private const int SUB_SPORT_DOWNHILL = 9;
    private const int SUB_SPORT_CYCLOCROSS = 11;
    private const int SUB_SPORT_HAND_CYCLING = 12;
    private const int SUB_SPORT_INDOOR_ROWING = 14;
    private const int SUB_SPORT_ELLIPTICAL = 15;
    private const int SUB_SPORT_STAIR_CLIMBING = 16;
    private const int SUB_SPORT_STRENGTH_TRAINING = 20;
    private const int SUB_SPORT_INDOOR_WALKING = 27;
    private const int SUB_SPORT_E_BIKE_FITNESS = 28;
    private const int SUB_SPORT_BACKCOUNTRY = 37;
    private const int SUB_SPORT_YOGA = 43;
    private const int SUB_SPORT_PILATES = 44;
    private const int SUB_SPORT_INDOOR_RUNNING = 45;
    private const int SUB_SPORT_GRAVEL_CYCLING = 46;
    private const int SUB_SPORT_E_BIKE_MOUNTAIN = 47;
    private const int SUB_SPORT_VIRTUAL_ACTIVITY = 58;
    private const int SUB_SPORT_HIIT = 70;
    private const int SUB_SPORT_PICKLEBALL = 84;
    private const int SUB_SPORT_PADEL = 85;
    private const int SUB_SPORT_INDOOR_HAND_CYCLING = 88;
    private const int SUB_SPORT_SQUASH = 94;
    private const int SUB_SPORT_BADMINTON = 95;
    private const int SUB_SPORT_RACQUETBALL = 96;
    private const int SUB_SPORT_TABLE_TENNIS = 97;
    private const int SUB_SPORT_ENDURO = 123;
    private const int SUB_SPORT_E_BIKE_ENDURO = 127;

    public static function resolve(?int $sport, ?int $subSport): ?SportType
    {
        return match ($sport) {
            self::SPORT_RUNNING => match (true) {
                self::SUB_SPORT_TRAIL === $subSport => SportType::TRAIL_RUN,
                in_array($subSport, [self::SUB_SPORT_TREADMILL, self::SUB_SPORT_INDOOR_RUNNING, self::SUB_SPORT_VIRTUAL_ACTIVITY], true) => SportType::VIRTUAL_RUN,
                default => SportType::RUN,
            },
            self::SPORT_CYCLING => match (true) {
                in_array($subSport, [self::SUB_SPORT_MOUNTAIN, self::SUB_SPORT_DOWNHILL, self::SUB_SPORT_CYCLOCROSS, self::SUB_SPORT_ENDURO], true) => SportType::MOUNTAIN_BIKE_RIDE,
                self::SUB_SPORT_GRAVEL_CYCLING === $subSport => SportType::GRAVEL_RIDE,
                in_array($subSport, [self::SUB_SPORT_INDOOR_CYCLING, self::SUB_SPORT_SPIN, self::SUB_SPORT_VIRTUAL_ACTIVITY], true) => SportType::VIRTUAL_RIDE,
                in_array($subSport, [self::SUB_SPORT_E_BIKE_FITNESS, self::SUB_SPORT_E_BIKE_MOUNTAIN, self::SUB_SPORT_E_BIKE_ENDURO], true) => SportType::E_BIKE_RIDE,
                in_array($subSport, [self::SUB_SPORT_HAND_CYCLING, self::SUB_SPORT_INDOOR_HAND_CYCLING], true) => SportType::HAND_CYCLE,
                default => SportType::RIDE,
            },
            self::SPORT_FITNESS_EQUIPMENT => match (true) {
                self::SUB_SPORT_INDOOR_ROWING === $subSport => SportType::VIRTUAL_ROW,
                self::SUB_SPORT_ELLIPTICAL === $subSport => SportType::ELLIPTICAL,
                self::SUB_SPORT_STAIR_CLIMBING === $subSport => SportType::STAIR_STEPPER,
                in_array($subSport, [self::SUB_SPORT_INDOOR_CYCLING, self::SUB_SPORT_SPIN], true) => SportType::VIRTUAL_RIDE,
                self::SUB_SPORT_TREADMILL === $subSport => SportType::VIRTUAL_RUN,
                self::SUB_SPORT_INDOOR_WALKING === $subSport => SportType::WALK,
                self::SUB_SPORT_PILATES === $subSport => SportType::PILATES,
                default => SportType::WORKOUT,
            },
            self::SPORT_SWIMMING => SportType::SWIM,
            self::SPORT_BASKETBALL => SportType::BASKETBALL,
            self::SPORT_SOCCER => SportType::SOCCER,
            self::SPORT_TENNIS => SportType::TENNIS,
            self::SPORT_TRAINING => match (true) {
                self::SUB_SPORT_STRENGTH_TRAINING === $subSport => SportType::WEIGHT_TRAINING,
                self::SUB_SPORT_YOGA === $subSport => SportType::YOGA,
                self::SUB_SPORT_PILATES === $subSport => SportType::PILATES,
                self::SUB_SPORT_HIIT === $subSport => SportType::HIIT,
                default => SportType::WORKOUT,
            },
            self::SPORT_WALKING => SportType::WALK,
            self::SPORT_CROSS_COUNTRY_SKIING => SportType::NORDIC_SKI,
            self::SPORT_ALPINE_SKIING => self::SUB_SPORT_BACKCOUNTRY === $subSport ? SportType::BACK_COUNTRY_SKI : SportType::ALPINE_SKI,
            self::SPORT_SNOWBOARDING => SportType::SNOWBOARD,
            self::SPORT_ROWING => self::SUB_SPORT_INDOOR_ROWING === $subSport ? SportType::VIRTUAL_ROW : SportType::ROWING,
            self::SPORT_HIKING => SportType::HIKE,
            self::SPORT_PADDLING, self::SPORT_CANOEING => SportType::CANOEING,
            self::SPORT_E_BIKING => SportType::E_BIKE_RIDE,
            self::SPORT_GOLF => SportType::GOLF,
            self::SPORT_INLINE_SKATING => SportType::INLINE_SKATE,
            self::SPORT_ROCK_CLIMBING => SportType::ROCK_CLIMBING,
            self::SPORT_SAILING => SportType::SAIL,
            self::SPORT_ICE_SKATING => SportType::ICE_SKATE,
            self::SPORT_SNOWSHOEING => SportType::SNOWSHOE,
            self::SPORT_STAND_UP_PADDLEBOARDING => SportType::STAND_UP_PADDLING,
            self::SPORT_SURFING => SportType::SURFING,
            self::SPORT_KAYAKING => SportType::KAYAKING,
            self::SPORT_WINDSURFING => SportType::WIND_SURF,
            self::SPORT_KITESURFING => SportType::KITE_SURF,
            // Garmin "floor climbing" tracks flights of stairs climbed.
            self::SPORT_FLOOR_CLIMBING => SportType::STAIR_STEPPER,
            self::SPORT_HIIT => SportType::HIIT,
            self::SPORT_RACKET => match ($subSport) {
                self::SUB_SPORT_PICKLEBALL => SportType::PICKLE_BALL,
                self::SUB_SPORT_PADEL => SportType::PADEL,
                self::SUB_SPORT_SQUASH => SportType::SQUASH,
                self::SUB_SPORT_BADMINTON => SportType::BADMINTON,
                self::SUB_SPORT_RACQUETBALL => SportType::RACQUET_BALL,
                self::SUB_SPORT_TABLE_TENNIS => SportType::TABLE_TENNIS,
                default => SportType::WORKOUT,
            },
            self::SPORT_WHEELCHAIR_PUSH_WALK, self::SPORT_WHEELCHAIR_PUSH_RUN => SportType::WHEELCHAIR,
            self::SPORT_CRICKET => SportType::CRICKET,
            self::SPORT_VOLLEYBALL => SportType::VOLLEYBALL,
            self::SPORT_DANCE => SportType::DANCE,
            default => null,
        };
    }
}
