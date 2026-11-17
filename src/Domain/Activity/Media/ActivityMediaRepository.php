<?php

declare(strict_types=1);

namespace App\Domain\Activity\Media;

use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\ValueObject\Time\Years;

interface ActivityMediaRepository extends DeletesActivityData
{
    public function findAll(): ActivityMediaItems;

    public function findByActivityIds(ActivityIds $activityIds): ActivityMediaItems;

    public function count(): int;

    public function findRandomFor(SportTypes $sportTypes, Years $years): ActivityMediaItem;
}
