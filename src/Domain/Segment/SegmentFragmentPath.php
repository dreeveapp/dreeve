<?php

declare(strict_types=1);

namespace App\Domain\Segment;

final readonly class SegmentFragmentPath
{
    public const string COLLECTION = 'segments';

    public static function match(string $path, ?string $subResource = null): ?SegmentId
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
            return SegmentId::fromString($matches[1]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function for(SegmentId $segmentId, ?string $subResource = null): string
    {
        return sprintf('%s/%s%s', self::COLLECTION, $segmentId, $subResource ? '/'.$subResource : '');
    }

    public static function cacheKey(SegmentId $segmentId, ?string $subResource = null): string
    {
        return sprintf(
            '%s.%s%s',
            self::COLLECTION,
            $segmentId->toUnprefixedString(),
            $subResource ? '.'.$subResource : ''
        );
    }
}
