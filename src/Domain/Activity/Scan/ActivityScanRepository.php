<?php

declare(strict_types=1);

namespace App\Domain\Activity\Scan;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Activity\Stream\StreamType;

interface ActivityScanRepository extends DeletesActivityData
{
    public function add(ActivityScan $activityScan): void;

    public function isScanned(ActivityId $activityId, ActivityScanType $type, string $subjectId = ''): bool;

    public function deleteForActivityAndType(ActivityId $activityId, ActivityScanType $type): void;

    public function deleteForSubject(ActivityScanType $type, string $subjectId): void;

    /**
     * @param list<StreamType> $requiredStreamTypes
     */
    public function findActivityIdsThatNeedScanning(
        ActivityScanType $type,
        string $subjectId,
        SportTypes $sportTypes,
        array $requiredStreamTypes,
    ): ActivityIds;
}
