<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Signature;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use Doctrine\DBAL\Connection;

final readonly class DbalActivityRouteSignatureRepository extends DbalRepository implements ActivityRouteSignatureRepository
{
    public function __construct(
        Connection $connection,
        private RouteGrid $routeGrid,
    ) {
        parent::__construct($connection);
    }

    public function add(ActivityRouteSignature $activityRouteSignature): void
    {
        $sql = 'INSERT INTO ActivityRouteSignature (activityId, polylineChecksum, cellCount, cells, waypoints)
                VALUES (:activityId, :polylineChecksum, :cellCount, :cells, :waypoints)';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityRouteSignature->getActivityId(),
            'polylineChecksum' => $activityRouteSignature->getPolylineChecksum(),
            'cellCount' => $activityRouteSignature->getCellCount(),
            'cells' => Json::encodeAndCompress($activityRouteSignature->getCells()->toArray()),
            'waypoints' => Json::encodeAndCompress($activityRouteSignature->getWaypoints()->toArray()),
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivityRouteSignature WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function findActivityIdsThatNeedRouteSignatureCalculation(): ActivityIds
    {
        $sql = 'SELECT Activity.activityId, Activity.polyline, ActivityRouteSignature.polylineChecksum
                FROM Activity
                LEFT JOIN ActivityRouteSignature ON ActivityRouteSignature.activityId = Activity.activityId
                WHERE Activity.polyline IS NOT NULL AND Activity.polyline <> ""
                ORDER BY Activity.activityId';

        return ActivityIds::fromArray(array_map(
            fn (array $result): ActivityId => ActivityId::fromString($result['activityId']),
            array_filter(
                $this->connection->executeQuery($sql)->fetchAllAssociative(),
                fn (array $result): bool => $this->routeGrid->checksumFor(
                    EncodedPolyline::fromString($result['polyline'])
                ) !== $result['polylineChecksum']
            )
        ));
    }
}
