<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\MediaLocation;

use App\Infrastructure\ValueObject\Geography\Coordinate;

final readonly class LocationSuggestion
{
    private function __construct(
        private Coordinate $coordinate,
        private LocationSuggestionConfidence $confidence,
        private string $reasoning,
    ) {
    }

    public static function create(
        Coordinate $coordinate,
        LocationSuggestionConfidence $confidence,
        string $reasoning,
    ): self {
        return new self(
            coordinate: $coordinate,
            confidence: $confidence,
            reasoning: $reasoning,
        );
    }

    public function getCoordinate(): Coordinate
    {
        return $this->coordinate;
    }

    public function getConfidence(): LocationSuggestionConfidence
    {
        return $this->confidence;
    }

    public function getReasoning(): string
    {
        return $this->reasoning;
    }
}
