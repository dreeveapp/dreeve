<?php

declare(strict_types=1);

namespace App\Domain\Activity\SportType;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\ValueObject\BackedEnumCollection;

/**
 * @extends BackedEnumCollection<\App\Domain\Activity\SportType\SportType>
 */
final class SportTypes extends BackedEnumCollection
{
    public function getItemClassName(): string
    {
        return SportType::class;
    }

    public static function thatSupportPeakPowerOutputs(ActivityType $activityType): SportTypes
    {
        return match ($activityType) {
            ActivityType::RIDE => self::fromArray([
                SportType::RIDE,
                SportType::MOUNTAIN_BIKE_RIDE,
                SportType::GRAVEL_RIDE,
                SportType::VIRTUAL_RIDE,
            ]),
            ActivityType::RUN => self::fromArray([
                SportType::RUN,
                SportType::TRAIL_RUN,
                SportType::VIRTUAL_RUN,
            ]),
            default => throw new \RuntimeException(sprintf('ActivityType "%s" does not support AllTimePeakPowers', $activityType->value)),
        };
    }

    public static function thatSupportImagesForStravaRewind(): SportTypes
    {
        return self::fromArray(array_filter(
            SportType::cases(),
            fn (SportType $sportType): bool => !$sportType->isVirtual()
        ));
    }

    public static function virtual(): SportTypes
    {
        return self::fromArray(array_filter(
            SportType::cases(),
            fn (SportType $sportType): bool => $sportType->isVirtual()
        ));
    }

    public static function thatSupportsBestEfforts(): SportTypes
    {
        $sportTypes = SportTypes::empty();

        foreach (ActivityType::cases() as $activityType) {
            if (!$activityType->supportsBestEffortsStats()) {
                continue;
            }

            foreach ($activityType->getSportTypes() as $sportType) {
                $sportTypes->add($sportType);
            }
        }

        return $sportTypes;
    }

    public static function all(): SportTypes
    {
        return self::fromArray(SportType::cases());
    }
}
