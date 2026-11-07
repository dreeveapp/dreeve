<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindTripSuggestions;

use App\Domain\Activity\ActivityIds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class TripSuggestion
{
    /**
     * @param string[] $countryCodes
     * @param string[] $places
     */
    private function __construct(
        private ActivityIds $activityIds,
        private SerializableDateTime $startDate,
        private SerializableDateTime $endDate,
        private array $countryCodes,
        private array $places,
    ) {
    }

    /**
     * @param string[] $countryCodes
     * @param string[] $places
     */
    public static function create(
        ActivityIds $activityIds,
        SerializableDateTime $startDate,
        SerializableDateTime $endDate,
        array $countryCodes,
        array $places,
    ): self {
        return new self(
            activityIds: $activityIds,
            startDate: $startDate,
            endDate: $endDate,
            countryCodes: $countryCodes,
            places: $places,
        );
    }

    public function getId(): TripSuggestionId
    {
        return TripSuggestionId::fromUnprefixed(md5(implode(',', $this->activityIds->map(strval(...)))));
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }

    public function getStartDate(): SerializableDateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): SerializableDateTime
    {
        return $this->endDate;
    }

    /**
     * @return string[]
     */
    public function getCountryCodes(): array
    {
        return $this->countryCodes;
    }

    /**
     * @return string[]
     */
    public function getPlaces(): array
    {
        return $this->places;
    }
}
