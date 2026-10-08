<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentActivityScan;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\Repository\DbalRepository;

final readonly class DbalSegmentActivityScanRepository extends DbalRepository implements SegmentActivityScanRepository
{
    public function add(SegmentActivityScan $segmentActivityScan): void
    {
        $sql = 'INSERT OR IGNORE INTO SegmentActivityScan (segmentId, activityId) VALUES (:segmentId, :activityId)';

        $this->connection->executeStatement($sql, [
            'segmentId' => $segmentActivityScan->getSegmentId(),
            'activityId' => $segmentActivityScan->getActivityId(),
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $this->connection->executeStatement('DELETE FROM SegmentActivityScan WHERE activityId = :activityId', [
            'activityId' => $activityId,
        ]);
    }

    public function deleteForSegment(SegmentId $segmentId): void
    {
        $this->connection->executeStatement('DELETE FROM SegmentActivityScan WHERE segmentId = :segmentId', [
            'segmentId' => $segmentId,
        ]);
    }

    public function findActivityIdsThatNeedScanning(Segment $segment): ActivityIds
    {
        $sql = 'SELECT Activity.activityId
                FROM Activity
                INNER JOIN ActivityStream ON ActivityStream.activityId = Activity.activityId AND ActivityStream.streamType = :streamType
                WHERE Activity.activityType = :activityType
                AND NOT EXISTS (
                    SELECT 1 FROM SegmentActivityScan
                    WHERE SegmentActivityScan.segmentId = :segmentId AND SegmentActivityScan.activityId = Activity.activityId
                )
                ORDER BY Activity.startDateTime ASC, Activity.activityId ASC';

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $this->connection->executeQuery($sql, [
                'streamType' => StreamType::LAT_LNG->value,
                'activityType' => $segment->getSportType()->getActivityType()->value,
                'segmentId' => $segment->getId(),
            ])->fetchFirstColumn()
        ));
    }
}
