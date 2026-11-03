<?php

declare(strict_types=1);

namespace App\Domain\Activity;

final readonly class ActivityReference
{
    public static function format(string $name, ActivityId $activityId): string
    {
        return sprintf('%s (%s)', $name, $activityId);
    }

    public static function parse(string $value): ActivityId
    {
        $value = trim($value);
        if (1 === preg_match('/\(('.preg_quote(ActivityId::getPrefix(), '/').'[^()\s]+)\)$/', $value, $matches)) {
            return ActivityId::fromString($matches[1]);
        }

        return ActivityId::fromString($value);
    }
}
