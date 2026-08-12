<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentId;

interface SegmentEffortRepository
{
    public function add(SegmentEffort $segmentEffort): void;

    public function deleteForActivity(ActivityId $activityId): void;

    public function find(SegmentEffortId $segmentEffortId): SegmentEffort;

    public function findTopXBySegmentId(SegmentId $segmentId, int $limit): SegmentEfforts;

    public function findBySegmentId(SegmentId $segmentId): SegmentEfforts;

    public function findByActivityId(ActivityId $activityId): SegmentEfforts;
}
