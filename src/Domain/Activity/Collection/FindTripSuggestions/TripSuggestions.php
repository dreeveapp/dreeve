<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindTripSuggestions;

use App\Domain\Activity\ActivityIds;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<TripSuggestion>
 */
final class TripSuggestions extends Collection
{
    public function getActivityIds(): ActivityIds
    {
        return ActivityIds::fromArray(array_merge(...$this->map(
            fn (TripSuggestion $tripSuggestion): array => $tripSuggestion->getActivityIds()->toArray()
        )))->unique();
    }

    public function getItemClassName(): string
    {
        return TripSuggestion::class;
    }
}
