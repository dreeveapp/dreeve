<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ActivityHydrator
{
    /**
     * @var list<string>
     */
    private const array COLUMNS = [
        'activityId', 'startDateTime', 'sportType', 'worldType', 'importSource',
        'externalReferenceId', 'name', 'description', 'distance', 'elevation',
        'startingCoordinateLatitude', 'startingCoordinateLongitude', 'calories', 'kilojoules',
        'averagePower', 'maxPower', 'averageSpeed', 'maxSpeed', 'averageHeartRate', 'maxHeartRate',
        'averageCadence', 'movingTimeInSeconds', 'elapsedTimeInSeconds', 'deviceName',
        'totalImageCount', 'localImagePaths', 'polyline', 'routeGeography', 'weather', 'gearId',
        'isCommute', 'workoutType',
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
    public static function hydrate(array $result): Activity
    {
        $startDateTime = SerializableDateTime::fromString($result['startDateTime']);
        $sportType = SportType::from($result['sportType']);

        return Activity::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: WorldType::from($result['worldType']),
            importSource: ImportSource::from($result['importSource']),
            externalReferenceId: ExternalReferenceId::fromOptionalString($result['externalReferenceId'] ?? null),
            name: '' !== trim((string) $result['name']) ? ActivityName::fromString($result['name']) : ActivityName::from($startDateTime, $sportType),
            description: $result['description'] ?: '',
            distance: Meter::from($result['distance'])->toKilometer(),
            elevation: Meter::from($result['elevation'] ?: 0),
            startingCoordinate: Coordinate::createFromOptionalLatAndLng(
                Latitude::fromOptionalString((string) $result['startingCoordinateLatitude']),
                Longitude::fromOptionalString((string) $result['startingCoordinateLongitude'])
            ),
            calories: (int) ($result['calories'] ?? 0),
            kilojoules: ((int) $result['kilojoules']) ?: null,
            averagePower: ((int) $result['averagePower']) ?: null,
            maxPower: ((int) $result['maxPower']) ?: null,
            averageSpeed: KmPerHour::from($result['averageSpeed']),
            maxSpeed: KmPerHour::from($result['maxSpeed']),
            averageHeartRate: isset($result['averageHeartRate']) ? (int) round($result['averageHeartRate']) : null,
            maxHeartRate: isset($result['maxHeartRate']) ? (int) round($result['maxHeartRate']) : null,
            averageCadence: isset($result['averageCadence']) ? (int) round($result['averageCadence']) : null,
            movingTimeInSeconds: $result['movingTimeInSeconds'] ?: 0,
            elapsedTimeInSeconds: $result['elapsedTimeInSeconds'] ?: 0,
            deviceName: $result['deviceName'],
            totalImageCount: $result['totalImageCount'] ?: 0,
            localImagePaths: $result['localImagePaths'] ? explode(',', (string) $result['localImagePaths']) : [],
            polyline: $result['polyline'],
            routeGeography: RouteGeography::create(Json::decode($result['routeGeography'] ?? '[]')),
            weather: $result['weather'],
            gearId: GearId::fromOptionalString($result['gearId']),
            isCommute: (bool) $result['isCommute'],
            workoutType: WorkoutType::tryFrom($result['workoutType'] ?? ''),
        );
    }
}
