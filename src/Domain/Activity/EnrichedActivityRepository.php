<?php

declare(strict_types=1);

namespace App\Domain\Activity;

interface EnrichedActivityRepository
{
    public function find(ActivityId $activityId): EnrichedActivity;

    /**
     * @return EnrichedActivity[]
     */
    public function findAll(): array;
}
