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
                       Activity.distance
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

        $matches = [];
        $startDateTimes = [];

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
            // Without the absolute ceiling the allowance grows with the route, leaving long routes
            // without any geometric constraint at all.
            $maxWaypointDrift = min(
                RouteGrid::MAX_WAYPOINT_DRIFT_RATIO * min($subjectDistance, $candidateDistance),
                RouteGrid::MAX_WAYPOINT_DRIFT_IN_METER,
            );
            $waypointDrift = $subjectWaypoints->medianDistanceInMeterTo($this->decodeWaypoints($candidate['waypoints']));
            if ($waypointDrift > $maxWaypointDrift) {
                continue;
            }

            $activityId = (string) $candidate['activityId'];
            $matches[] = $candidate;
            $startDateTimes[$activityId] = SerializableDateTime::fromString((string) $candidate['startDateTime']);
        }

        $timestamps = array_map(fn (SerializableDateTime $startDateTime): int => $startDateTime->getTimestamp(), $startDateTimes);
        // Sorting a copy keeps $matches in fastest-first order; ties fall back to that same order.
        arsort($timestamps);
        $recencyRanks = array_flip(array_keys($timestamps));

        $routeMatches = RouteMatches::empty();
        $rank = 0;

        foreach ($matches as $match) {
            $activityId = (string) $match['activityId'];

            $routeMatches->add(RouteMatch::fromState(
                activityId: ActivityId::fromString($activityId),
                rank: ++$rank,
                recencyRank: $recencyRanks[$activityId] + 1,
                name: (string) $match['name'],
                distance: Meter::from((int) $match['distance'])->toKilometer(),
                movingTimeInSeconds: (int) $match['movingTimeInSeconds'],
                startDateTime: $startDateTimes[$activityId],
                isCurrentActivity: $activityId === (string) $query->getActivityId(),
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
