<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentType;

interface SegmentEffortRepository extends DeletesActivityData
{
    public function add(SegmentEffort $segmentEffort): void;

    public function deleteForActivityAndSegmentType(ActivityId $activityId, SegmentType $segmentType): void;

    public function deleteForSegment(SegmentId $segmentId): void;

    public function find(SegmentEffortId $segmentEffortId): SegmentEffort;

    public function findTopXBySegmentId(SegmentId $segmentId, int $limit): SegmentEfforts;

    public function findBySegmentId(SegmentId $segmentId): SegmentEfforts;

    public function findByActivityId(ActivityId $activityId): SegmentEfforts;
}
