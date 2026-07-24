<?php

declare(strict_types=1);

namespace App\Domain\Activity\Split;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Repository\DbalRepository;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalActivitySplitRepository extends DbalRepository implements ActivitySplitRepository
{
    public function findBy(ActivityId $activityId, UnitSystem $unitSystem): ActivitySplits
    {
        $sql = 'SELECT * FROM ActivitySplit 
         WHERE activityId = :activityId AND unitSystem = :unitSystem
         ORDER BY splitNumber ASC';

        $results = $this->connection->executeQuery($sql, [
            'activityId' => $activityId,
            'unitSystem' => $unitSystem->value,
        ])->fetchAllAssociative();

        return ActivitySplits::fromArray(array_map(
            $this->hydrate(...),
            $results
        ));
    }

    public function isImportedForActivity(ActivityId $activityId): bool
    {
        return $this->connection
                ->executeQuery('SELECT COUNT(*) FROM ActivitySplit WHERE activityId = :activityId', [
                    'activityId' => $activityId,
                ])
                ->fetchOne() > 0;
    }

    public function add(ActivitySplit $activitySplit): void
    {
        $sql = 'INSERT INTO ActivitySplit (
            activityId, unitSystem, splitNumber, distance, elapsedTimeInSeconds, movingTimeInSeconds,
            elevationDifference, averageSpeed, minAverageSpeed, maxAverageSpeed, paceZone, gapPaceInSecondsPerKm
        ) VALUES(
            :activityId, :unitSystem, :splitNumber, :distance, :elapsedTimeInSeconds, :movingTimeInSeconds,
            :elevationDifference, :averageSpeed, :minAverageSpeed, :maxAverageSpeed, :paceZone, :gapPaceInSecondsPerKm
        )';

        $this->connection->executeStatement($sql, [
            'activityId' => $activitySplit->getActivityId(),
            'unitSystem' => $activitySplit->getUnitSystem()->value,
            'splitNumber' => $activitySplit->getSplitNumber(),
            'distance' => $activitySplit->getDistance()->toFloat(),
            'elapsedTimeInSeconds' => $activitySplit->getElapsedTimeInSeconds(),
            'movingTimeInSeconds' => $activitySplit->getMovingTimeInSeconds(),
            'elevationDifference' => $activitySplit->getElevationDifference()->toFloat(),
            'averageSpeed' => $activitySplit->getAverageSpeed()->toFloat(),
            'minAverageSpeed' => $activitySplit->getMinAverageSpeed()->toFloat(),
            'maxAverageSpeed' => $activitySplit->getMaxAverageSpeed()->toFloat(),
            'paceZone' => $activitySplit->getPaceZone(),
            'gapPaceInSecondsPerKm' => $activitySplit->getGapPaceInSecondsPerKm()?->toFloat(),
        ]);
    }

    public function update(ActivitySplit $activitySplit): void
    {
        $sql = 'UPDATE ActivitySplit SET gapPaceInSecondsPerKm = :gapPaceInSecondsPerKm
            WHERE activityId = :activityId AND unitSystem = :unitSystem AND splitNumber = :splitNumber';

        $this->connection->executeStatement($sql, [
            'activityId' => $activitySplit->getActivityId(),
            'unitSystem' => $activitySplit->getUnitSystem()->value,
            'splitNumber' => $activitySplit->getSplitNumber(),
            'gapPaceInSecondsPerKm' => $activitySplit->getGapPaceInSecondsPerKm()?->toFloat(),
        ]);
    }

    public function findActivityIdsWithoutGap(): ActivityIds
    {
        $supportedSportTypes = array_map(
            fn (SportType $sportType) => $sportType->value,
            array_filter(
                SportType::cases(),
                fn (SportType $sportType): bool => $sportType->getActivityType()->supportsGapStats(),
            ),
        );

        $sql = 'SELECT s.activityId FROM ActivitySplit s
                INNER JOIN Activity a ON a.activityId = s.activityId
                WHERE a.sportType IN (:sportTypes)
                GROUP BY s.activityId
                HAVING MAX(s.gapPaceInSecondsPerKm) IS NULL
                ORDER BY s.activityId';

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $this->connection->executeQuery($sql, [
                'sportTypes' => $supportedSportTypes,
            ], [
                'sportTypes' => ArrayParameterType::STRING,
            ])->fetchFirstColumn()
        ));
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivitySplit WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivitySplit
    {
        return ActivitySplit::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            unitSystem: UnitSystem::from($result['unitSystem']),
            splitNumber: $result['splitNumber'],
            distance: Meter::from($result['distance']),
            elapsedTimeInSeconds: $result['elapsedTimeInSeconds'],
            movingTimeInSeconds: $result['movingTimeInSeconds'],
            elevationDifference: Meter::from($result['elevationDifference']),
            averageSpeed: MetersPerSecond::from($result['averageSpeed']),
            minAverageSpeed: MetersPerSecond::from($result['minAverageSpeed']),
            maxAverageSpeed: MetersPerSecond::from($result['maxAverageSpeed']),
            paceZone: $result['paceZone'],
            gapPaceInSecondsPerKm: isset($result['gapPaceInSecondsPerKm']) ? SecPerKm::from((float) $result['gapPaceInSecondsPerKm']) : null,
        );
    }
}
