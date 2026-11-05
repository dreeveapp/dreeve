<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;

interface ActivityCollectionRepository
{
    public function add(ActivityCollection $activityCollection): void;

    public function update(ActivityCollection $activityCollection): void;

    public function delete(ActivityCollection $activityCollection): void;

    public function find(ActivityCollectionId $activityCollectionId): ActivityCollection;

    public function findAll(): ActivityCollections;

    public function findForActivity(ActivityId $activityId): ActivityCollections;

    public function hasActivityCollectionsWithActivities(): bool;

    /**
     * @return list<ActivityCollectionId>
     */
    public function findIdsWithActivities(): array;
}
