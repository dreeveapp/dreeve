<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\FindGearComponentStats;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class FindGearComponentStatsResponse implements Response
{
    public function __construct(
        private Kilometer $distance,
        private Seconds $movingTime,
        private int $numberOfActivities,
        private ?SerializableDateTime $firstUsedOn,
        private ?SerializableDateTime $lastUsedOn,
    ) {
    }

    public function getDistance(): Kilometer
    {
        return $this->distance;
    }

    public function getMovingTime(): Seconds
    {
        return $this->movingTime;
    }

    public function getNumberOfActivities(): int
    {
        return $this->numberOfActivities;
    }

    public function getFirstUsedOn(): ?SerializableDateTime
    {
        return $this->firstUsedOn;
    }

    public function getLastUsedOn(): ?SerializableDateTime
    {
        return $this->lastUsedOn;
    }
}
