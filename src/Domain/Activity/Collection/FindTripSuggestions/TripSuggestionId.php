<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindTripSuggestions;

use App\Infrastructure\ValueObject\Identifier\Identifier;

final readonly class TripSuggestionId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'tripSuggestion-';
    }
}
