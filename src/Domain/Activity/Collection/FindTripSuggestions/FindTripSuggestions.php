<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindTripSuggestions;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\Collection\FindTripSuggestions\FindTripSuggestionsResponse>
 */
final readonly class FindTripSuggestions implements Query
{
}
