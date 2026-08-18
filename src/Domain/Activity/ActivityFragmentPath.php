<?php

declare(strict_types=1);

namespace App\Domain\Activity;

final readonly class ActivityFragmentPath
{
    public const string COLLECTION = 'activities';

    public static function match(string $path, ?string $subResource = null): ?ActivityId
    {
        $pattern = sprintf(
            '#^%s/([^/]+)%s$#',
            self::COLLECTION,
            $subResource ? '/'.preg_quote($subResource, '#') : ''
        );

        if (!preg_match($pattern, $path, $matches)) {
            return null;
        }

        try {
            return ActivityId::fromString($matches[1]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function for(ActivityId $activityId, ?string $subResource = null): string
    {
        return sprintf('%s/%s%s', self::COLLECTION, $activityId, $subResource ? '/'.$subResource : '');
    }

    public static function cacheKey(ActivityId $activityId, ?string $subResource = null): string
    {
        return sprintf(
            '%s.%s%s',
            self::COLLECTION,
            $activityId->toUnprefixedString(),
            $subResource ? '.'.$subResource : ''
        );
    }
}
