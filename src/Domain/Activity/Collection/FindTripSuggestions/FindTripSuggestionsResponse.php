<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindTripSuggestions;

use App\Infrastructure\CQRS\Query\Response;

final readonly class FindTripSuggestionsResponse implements Response
{
    public function __construct(
        private TripSuggestions $tripSuggestions,
    ) {
    }

    public function getTripSuggestions(): TripSuggestions
    {
        return $this->tripSuggestions;
    }
}
