<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;

interface ActivityStreamRepository
{
    public function add(ActivityStream $stream): void;

    public function deleteForActivity(ActivityId $activityId): void;

    public function hasOneForActivityAndStreamType(ActivityId $activityId, StreamType $streamType): bool;

    public function findActivityIdsByStreamType(StreamType $streamType): ActivityIds;

    public function findOneByActivityAndStreamType(ActivityId $activityId, StreamType $streamType): ActivityStream;

    public function findByActivityId(ActivityId $activityId): ActivityStreams;
}
