<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ActivityName extends NonEmptyStringLiteral
{
    public static function from(SerializableDateTime $date, SportType $sportType): self
    {
        $words = preg_split('/(?=[A-Z])/', $sportType->value, -1, PREG_SPLIT_NO_EMPTY);
        assert(is_array($words));

        return self::fromString(sprintf('%s %s', self::getTimeOfDay($date), implode(' ', $words)));
    }

    public static function forDisplay(string $name): string
    {
        $name = str_replace('Zwift - ', '', $name);
        $name = (string) preg_replace('/\s*#sfs-\S+/', '', $name);

        return trim($name);
    }

    private static function getTimeOfDay(SerializableDateTime $date): string
    {
        $hour = $date->getHourWithoutLeadingZero();

        return match (true) {
            $hour >= 5 && $hour < 12 => 'Morning',
            $hour >= 12 && $hour < 17 => 'Afternoon',
            $hour >= 17 && $hour < 21 => 'Evening',
            default => 'Night',
        };
    }
}
