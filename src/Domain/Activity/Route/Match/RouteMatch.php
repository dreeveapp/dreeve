<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class RouteMatch
{
    use ProvideTimeFormats;

    private function __construct(
        private ActivityId $activityId,
        private int $rank,
        private int $recencyRank,
        private int $speedRank,
        private string $name,
        private SportType $sportType,
        private Kilometer $distance,
        private int $movingTimeInSeconds,
        private KmPerHour $averageSpeed,
        private SerializableDateTime $startDateTime,
        private bool $isCurrentActivity,
    ) {
    }

    public static function fromState(
        ActivityId $activityId,
        int $rank,
        int $recencyRank,
        int $speedRank,
        string $name,
        SportType $sportType,
        Kilometer $distance,
        int $movingTimeInSeconds,
        KmPerHour $averageSpeed,
        SerializableDateTime $startDateTime,
        bool $isCurrentActivity,
    ): self {
        return new self(
            activityId: $activityId,
            rank: $rank,
            recencyRank: $recencyRank,
            speedRank: $speedRank,
            name: $name,
            sportType: $sportType,
            distance: $distance,
            movingTimeInSeconds: $movingTimeInSeconds,
            averageSpeed: $averageSpeed,
            startDateTime: $startDateTime,
            isCurrentActivity: $isCurrentActivity,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function getRecencyRank(): int
    {
        return $this->recencyRank;
    }

    public function getSpeedRank(): int
    {
        return $this->speedRank;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSportType(): SportType
    {
        return $this->sportType;
    }

    public function getDistance(): Kilometer
    {
        return $this->distance;
    }

    public function getMovingTimeInSeconds(): int
    {
        return $this->movingTimeInSeconds;
    }

    public function getMovingTimeFormatted(): string
    {
        return $this->formatDurationAsClock($this->movingTimeInSeconds);
    }

    public function getAverageSpeed(): KmPerHour
    {
        return $this->averageSpeed;
    }

    public function getStartDateTime(): SerializableDateTime
    {
        return $this->startDateTime;
    }

    public function isCurrentActivity(): bool
    {
        return $this->isCurrentActivity;
    }
}
