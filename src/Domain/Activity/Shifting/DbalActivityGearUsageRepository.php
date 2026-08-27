<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Repository\DbalRepository;

final readonly class DbalActivityGearUsageRepository extends DbalRepository implements ActivityGearUsageRepository
{
    public function add(ActivityGearUsage $activityGearUsage): void
    {
        $sql = 'INSERT INTO ActivityGearUsage (activityId, position, gearNumber, teeth, timeInSeconds, shiftCount)
                VALUES (:activityId, :position, :gearNumber, :teeth, :timeInSeconds, :shiftCount)';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityGearUsage->getActivityId(),
            'position' => $activityGearUsage->getPosition()->value,
            'gearNumber' => $activityGearUsage->getGearNumber(),
            'teeth' => $activityGearUsage->getTeeth(),
            'timeInSeconds' => $activityGearUsage->getTimeInSeconds(),
            'shiftCount' => $activityGearUsage->getShiftCount(),
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivityGearUsage WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function findByActivity(ActivityId $activityId): ActivityGearUsages
    {
        $sql = 'SELECT * FROM ActivityGearUsage
                WHERE activityId = :activityId
                ORDER BY position, gearNumber';

        return ActivityGearUsages::fromArray(array_map(
            $this->hydrate(...),
            $this->connection->executeQuery($sql, [
                'activityId' => $activityId,
            ])->fetchAllAssociative()
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivityGearUsage
    {
        return ActivityGearUsage::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            position: GearPosition::from($result['position']),
            gearNumber: (int) $result['gearNumber'],
            teeth: (int) $result['teeth'],
            timeInSeconds: (int) $result['timeInSeconds'],
            shiftCount: (int) $result['shiftCount'],
        );
    }
}
