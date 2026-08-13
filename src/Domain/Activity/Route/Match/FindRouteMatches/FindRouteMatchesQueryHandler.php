<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match\FindRouteMatches;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Route\Match\RouteMatch;
use App\Domain\Activity\Route\Match\RouteMatches;
use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Domain\Activity\Route\Signature\RouteWaypoints;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindRouteMatchesQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindRouteMatches);

        $subject = $this->connection->executeQuery(
            <<<SQL
                SELECT ActivityRouteSignature.cellCount,
                       ActivityRouteSignature.cells,
                       ActivityRouteSignature.waypoints,
                       Activity.activityType,
                       Activity.worldType,
                       Activity.distance,
                       Activity.movingTimeInSeconds
                FROM ActivityRouteSignature
                INNER JOIN Activity ON Activity.activityId = ActivityRouteSignature.activityId
                WHERE ActivityRouteSignature.activityId = :activityId
            SQL,
            ['activityId' => (string) $query->getActivityId()]
        )->fetchAssociative();

        if (false === $subject) {
            return new FindRouteMatchesResponse(RouteMatches::empty());
        }

        $subjectCellCount = (int) $subject['cellCount'];
        if ($subjectCellCount < RouteGrid::MIN_CELL_COUNT) {
            return new FindRouteMatchesResponse(RouteMatches::empty());
        }

        $subjectCells = $this->decodeCells($subject['cells']);
        $subjectWaypoints = $this->decodeWaypoints($subject['waypoints']);
        $subjectDistance = (int) $subject['distance'];
        $subjectMovingTimeInSeconds = (int) $subject['movingTimeInSeconds'];

        $candidates = $this->connection->executeQuery(
            <<<SQL
                SELECT ActivityRouteSignature.activityId,
                       ActivityRouteSignature.cellCount,
                       ActivityRouteSignature.cells,
                       ActivityRouteSignature.waypoints,
                       Activity.name,
                       Activity.distance,
                       Activity.movingTimeInSeconds,
                       Activity.startDateTime
                FROM ActivityRouteSignature
                INNER JOIN Activity ON Activity.activityId = ActivityRouteSignature.activityId
                WHERE Activity.activityType IS :activityType
                  AND Activity.worldType IS :worldType
                  AND Activity.distance BETWEEN :minDistance AND :maxDistance
                  AND ActivityRouteSignature.cellCount >= :minCellCount
                  AND ActivityRouteSignature.cellCount BETWEEN :minCandidateCellCount AND :maxCandidateCellCount
                ORDER BY Activity.movingTimeInSeconds ASC,
                         Activity.startDateTime ASC,
                         ActivityRouteSignature.activityId ASC
            SQL,
            [
                'activityType' => $subject['activityType'],
                'worldType' => $subject['worldType'],
                'minDistance' => (int) floor($subjectDistance * RouteGrid::MAX_DISTANCE_RATIO),
                'maxDistance' => (int) ceil($subjectDistance / RouteGrid::MAX_DISTANCE_RATIO),
                'minCellCount' => RouteGrid::MIN_CELL_COUNT,
                'minCandidateCellCount' => (int) floor($subjectCellCount * RouteGrid::THRESHOLD),
                'maxCandidateCellCount' => (int) ceil($subjectCellCount / RouteGrid::THRESHOLD),
            ]
        )->fetchAllAssociative();

        $routeMatches = RouteMatches::empty();
        $rank = 0;

        foreach ($candidates as $candidate) {
            $candidateCellCount = (int) $candidate['cellCount'];
            $candidateCells = $this->decodeCells($candidate['cells']);

            // Passing the smaller set first keeps the intersection linear in the smaller set.
            $intersection = $candidateCellCount <= $subjectCellCount
                ? count(array_intersect_key($candidateCells, $subjectCells))
                : count(array_intersect_key($subjectCells, $candidateCells));

            if ($intersection < RouteGrid::THRESHOLD * max($subjectCellCount, $candidateCellCount)) {
                continue;
            }

            $candidateDistance = (int) $candidate['distance'];
            $waypointDrift = $subjectWaypoints->medianDistanceInMeterTo($this->decodeWaypoints($candidate['waypoints']));
            if ($waypointDrift > RouteGrid::MAX_WAYPOINT_DRIFT_RATIO * min($subjectDistance, $candidateDistance)) {
                continue;
            }

            $activityId = ActivityId::fromString((string) $candidate['activityId']);
            $movingTimeInSeconds = (int) $candidate['movingTimeInSeconds'];

            $routeMatches->add(RouteMatch::fromState(
                activityId: $activityId,
                rank: ++$rank,
                name: (string) $candidate['name'],
                distance: Meter::from($candidateDistance)->toKilometer(),
                movingTimeInSeconds: $movingTimeInSeconds,
                startDateTime: SerializableDateTime::fromString((string) $candidate['startDateTime']),
                isCurrentActivity: (string) $activityId === (string) $query->getActivityId(),
                timeDeltaInSeconds: $movingTimeInSeconds - $subjectMovingTimeInSeconds,
            ));
        }

        return new FindRouteMatchesResponse($routeMatches);
    }

    /**
     * @return array<int, int>
     */
    private function decodeCells(string $cells): array
    {
        $decoded = Json::uncompressAndDecode($cells);
        assert(is_array($decoded));

        return array_flip($decoded);
    }

    private function decodeWaypoints(string $waypoints): RouteWaypoints
    {
        $decoded = Json::uncompressAndDecode($waypoints);
        assert(is_array($decoded));

        return RouteWaypoints::fromArray(array_values(array_map(intval(...), $decoded)));
    }
}
