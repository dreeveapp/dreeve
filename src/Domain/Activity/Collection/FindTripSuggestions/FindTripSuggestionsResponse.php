<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindTripSuggestions;

use App\Infrastructure\CQRS\Query\Response;

final readonly class FindTripSuggestionsResponse implements Response
{
    public function __construct(
        /** @var TripSuggestion[] */
        private array $tripSuggestions,
    ) {
    }

    /**
     * @return TripSuggestion[]
     */
    public function getTripSuggestions(): array
    {
        return $this->tripSuggestions;
    }
}
