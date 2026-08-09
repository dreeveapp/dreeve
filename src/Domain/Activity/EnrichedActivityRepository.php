<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

interface EnrichedActivityRepository
{
    public function find(ActivityId $activityId): EnrichedActivity;

    /**
     * @return EnrichedActivity[]
     */
    public function findAll(): array;

    /**
     * @return EnrichedActivity[]
     */
    public function findByIds(ActivityIds $activityIds): array;

    /**
     * @return EnrichedActivity[]
     */
    public function findByDateRange(SerializableDateTime $from, SerializableDateTime $till): array;
}
