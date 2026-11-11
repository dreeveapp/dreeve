<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\Name;

final readonly class SegmentHydrator
{
    /**
     * @var list<string>
     */
    private const array COLUMNS = [
        'segmentId', 'name', 'sportType', 'distance', 'maxGradient', 'isFavourite',
        'climbCategory', 'deviceName', 'countryCode', 'detailsHaveBeenImported', 'polyline',
        'startingCoordinateLatitude', 'startingCoordinateLongitude', 'averageGradient', 'type',
    ];

    public static function columns(?string $alias = null): string
    {
        if (is_null($alias)) {
            return implode(', ', self::COLUMNS);
        }

        return implode(', ', array_map(
            static fn (string $column): string => $alias.'.'.$column,
            self::COLUMNS
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    public static function hydrate(array $result): Segment
    {
        return Segment::fromState(
            segmentId: SegmentId::fromString($result['segmentId']),
            name: Name::fromString($result['name']),
            sportType: SportType::from($result['sportType']),
            distance: Meter::from($result['distance'])->toKilometer(),
            maxGradient: $result['maxGradient'],
            isFavourite: (bool) $result['isFavourite'],
            climbCategory: $result['climbCategory'],
            deviceName: $result['deviceName'],
            countryCode: $result['countryCode'],
            detailsHaveBeenImported: (bool) $result['detailsHaveBeenImported'],
            polyline: EncodedPolyline::fromOptionalString($result['polyline']),
            startingCoordinate: Coordinate::createFromOptionalLatAndLng(
                Latitude::fromOptionalString((string) $result['startingCoordinateLatitude']),
                Longitude::fromOptionalString((string) $result['startingCoordinateLongitude'])
            ),
            averageGradient: isset($result['averageGradient']) ? (float) $result['averageGradient'] : null,
            type: SegmentType::from($result['type']),
        );
    }
}
