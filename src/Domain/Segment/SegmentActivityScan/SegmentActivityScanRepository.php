<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentActivityScan;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentId;

interface SegmentActivityScanRepository
{
    public function add(SegmentActivityScan $segmentActivityScan): void;

    public function deleteForActivity(ActivityId $activityId): void;

    public function deleteForSegment(SegmentId $segmentId): void;

    public function findActivityIdsThatNeedScanning(Segment $segment): ActivityIds;
}
