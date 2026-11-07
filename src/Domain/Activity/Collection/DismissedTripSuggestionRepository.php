<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityIds;

interface DismissedTripSuggestionRepository
{
    public function findActivityIds(): ActivityIds;

    public function add(ActivityIds $activityIds): void;
}
