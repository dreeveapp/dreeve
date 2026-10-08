<?php

declare(strict_types=1);

namespace App\Domain\Activity\Scan;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Repository\DbalRepository;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalActivityScanRepository extends DbalRepository implements ActivityScanRepository
{
    public function add(ActivityScan $activityScan): void
    {
        $sql = 'INSERT OR IGNORE INTO ActivityScan (activityId, type, subjectId) VALUES (:activityId, :type, :subjectId)';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityScan->getActivityId(),
            'type' => $activityScan->getType()->value,
            'subjectId' => $activityScan->getSubjectId(),
        ]);
    }

    public function isScanned(ActivityId $activityId, ActivityScanType $type, string $subjectId = ''): bool
    {
        return false !== $this->connection->executeQuery(
            'SELECT 1 FROM ActivityScan WHERE activityId = :activityId AND type = :type AND subjectId = :subjectId',
            [
                'activityId' => $activityId,
                'type' => $type->value,
                'subjectId' => $subjectId,
            ]
        )->fetchOne();
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $this->connection->executeStatement('DELETE FROM ActivityScan WHERE activityId = :activityId', [
            'activityId' => $activityId,
        ]);
    }

    public function deleteForSubject(ActivityScanType $type, string $subjectId): void
    {
        $this->connection->executeStatement('DELETE FROM ActivityScan WHERE type = :type AND subjectId = :subjectId', [
            'type' => $type->value,
            'subjectId' => $subjectId,
        ]);
    }

    public function findActivityIdsThatNeedScanning(
        ActivityScanType $type,
        string $subjectId,
        SportTypes $sportTypes,
        array $requiredStreamTypes,
    ): ActivityIds {
        $sql = 'SELECT Activity.activityId
                FROM Activity
                WHERE Activity.sportType IN (:sportTypes)
                AND NOT EXISTS (
                    SELECT 1 FROM ActivityScan
                    WHERE ActivityScan.activityId = Activity.activityId
                    AND ActivityScan.type = :type AND ActivityScan.subjectId = :subjectId
                )';

        $params = [
            'type' => $type->value,
            'subjectId' => $subjectId,
            'sportTypes' => array_map(fn (SportType $sportType): string => $sportType->value, $sportTypes->toArray()),
        ];
        foreach ($requiredStreamTypes as $index => $streamType) {
            $sql .= sprintf(' AND EXISTS (
                    SELECT 1 FROM ActivityStream
                    WHERE ActivityStream.activityId = Activity.activityId
                    AND ActivityStream.streamType = :streamType%1$d AND ActivityStream.dataSize > 0
                )', $index);
            $params['streamType'.$index] = $streamType->value;
        }
        $sql .= ' ORDER BY Activity.startDateTime ASC, Activity.activityId ASC';

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $this->connection->executeQuery($sql, $params, [
                'sportTypes' => ArrayParameterType::STRING,
            ])->fetchFirstColumn()
        ));
    }
}
