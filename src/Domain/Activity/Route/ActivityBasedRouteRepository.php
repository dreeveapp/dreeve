<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;

final readonly class ActivityBasedRouteRepository extends DbalRepository implements RouteRepository
{
    public function findAll(): Routes
    {
        $query = 'SELECT activityId, name, distance, polyline, routeGeography, sportType, startDateTime, isCommute, workoutType
                    FROM Activity
                    WHERE sportType IN (:sportTypes)
                    AND polyline IS NOT NULL AND polyline <> ""
                    AND routeGeography IS NOT NULL AND routeGeography <> ""
                    AND JSON_EXTRACT(routeGeography, "$.country_code") IS NOT NULL
                    AND worldType = :worldType';

        $results = $this->connection->executeQuery(
            sql: $query,
            params: [
                'sportTypes' => array_map(
                    fn (SportType $sportType) => $sportType->value,
                    array_filter(
                        SportType::cases(),
                        fn (SportType $sportType): bool => $sportType->supportsReverseGeocoding()
                    )
                ),
                'worldType' => WorldType::REAL_WORLD->value,
            ],
            types: [
                'sportTypes' => ArrayParameterType::STRING,
            ]
        )->fetchAllAssociative();

        $routes = Routes::empty();
        foreach ($results as $result) {
            $routes->add(Route::create(
                activityId: ActivityId::fromString($result['activityId']),
                name: $result['name'],
                distance: Meter::from($result['distance'])->toKilometer(),
                coordinates: EncodedPolyline::fromString($result['polyline'])->decodeAndPairLatLng(),
                routeGeography: RouteGeography::create(Json::decode($result['routeGeography'])),
                sportType: SportType::from($result['sportType']),
                isCommute: (bool) $result['isCommute'],
                workoutType: WorkoutType::tryFrom($result['workoutType'] ?? ''),
                on: SerializableDateTime::fromString($result['startDateTime']),
            ));
        }

        return $routes;
    }
}
