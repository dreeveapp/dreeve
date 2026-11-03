<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

interface ActivityCollectionRepository
{
    public function add(ActivityCollection $activityCollection): void;

    public function find(ActivityCollectionId $activityCollectionId): ActivityCollection;

    public function findAll(): ActivityCollections;
}
