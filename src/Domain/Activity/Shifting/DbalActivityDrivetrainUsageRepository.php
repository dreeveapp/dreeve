<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Repository\DbalRepository;

final readonly class DbalActivityDrivetrainUsageRepository extends DbalRepository implements ActivityDrivetrainUsageRepository
{
    public function add(ActivityDrivetrainUsage $activityDrivetrainUsage): void
    {
        $sql = 'INSERT INTO ActivityDrivetrainUsage (activityId, position, gearNumber, teeth, timeInSeconds, shiftCount)
                VALUES (:activityId, :position, :gearNumber, :teeth, :timeInSeconds, :shiftCount)';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityDrivetrainUsage->getActivityId(),
            'position' => $activityDrivetrainUsage->getPosition()->value,
            'gearNumber' => $activityDrivetrainUsage->getGearNumber(),
            'teeth' => $activityDrivetrainUsage->getTeeth(),
            'timeInSeconds' => $activityDrivetrainUsage->getTimeInSeconds(),
            'shiftCount' => $activityDrivetrainUsage->getShiftCount(),
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivityDrivetrainUsage WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function findByActivity(ActivityId $activityId): ActivityDrivetrainUsages
    {
        $sql = 'SELECT * FROM ActivityDrivetrainUsage
                WHERE activityId = :activityId
                ORDER BY position, gearNumber';

        return ActivityDrivetrainUsages::fromArray(array_map(
            $this->hydrate(...),
            $this->connection->executeQuery($sql, [
                'activityId' => $activityId,
            ])->fetchAllAssociative()
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivityDrivetrainUsage
    {
        return ActivityDrivetrainUsage::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            position: DrivetrainPosition::from($result['position']),
            gearNumber: (int) $result['gearNumber'],
            teeth: (int) $result['teeth'],
            timeInSeconds: (int) $result['timeInSeconds'],
            shiftCount: (int) $result['shiftCount'],
        );
    }
}
