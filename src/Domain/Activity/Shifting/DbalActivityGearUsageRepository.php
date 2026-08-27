<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportIds;
use App\Domain\Import\FileImportStatus;
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
                WHERE activityId = :activityId AND position != :none
                ORDER BY position, gearNumber';

        return ActivityGearUsages::fromArray(array_map(
            $this->hydrate(...),
            $this->connection->executeQuery($sql, [
                'activityId' => $activityId,
                'none' => GearPosition::NONE->value,
            ])->fetchAllAssociative()
        ));
    }

    public function findFileImportIdsThatNeedShiftingExtraction(): FileImportIds
    {
        $sql = 'SELECT f.fileImportId FROM FileImport f
                WHERE f.status = :status
                AND f.source = :source
                AND f.activityId IS NOT NULL
                AND f.fileContents IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM ActivityGearUsage g
                    WHERE g.activityId = f.activityId
                )
                ORDER BY f.activityId';

        return FileImportIds::fromArray(array_map(
            FileImportId::fromString(...),
            $this->connection->executeQuery($sql, [
                'status' => FileImportStatus::SUCCESS->value,
                'source' => ImportSource::FIT_FILE->value,
            ])->fetchFirstColumn()
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
