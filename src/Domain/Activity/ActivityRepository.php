<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

interface ActivityRepository
{
    public function find(ActivityId $activityId): Activity;

    public function findAll(): Activities;

    public function findMostRecent(int $limit, ?SportTypes $restrictToSportTypes = null, bool $onlyActivitiesWithARoute = false): Activities;

    public function findByIds(ActivityIds $activityIds): Activities;

    public function findByDateRange(SerializableDateTime $from, SerializableDateTime $till): Activities;

    public function findWithRawData(ActivityId $activityId): ActivityWithRawData;

    public function exists(ActivityId $activityId): bool;

    public function add(ActivityWithRawData $activityWithRawData): void;

    public function update(ActivityWithRawData $activityWithRawData): void;

    public function delete(ActivityId $activityId): void;

    public function activityNeedsStreamImport(ActivityId $activityId): bool;

    public function markActivityStreamsAsImported(ActivityId $activityId): void;

    public function markActivitiesForDeletion(ActivityIds $activityIds): void;
}
