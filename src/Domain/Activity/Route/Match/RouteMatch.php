<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class RouteMatch
{
    use ProvideTimeFormats;

    private function __construct(
        private ActivityId $activityId,
        private int $rank,
        private string $name,
        private Kilometer $distance,
        private int $movingTimeInSeconds,
        private SerializableDateTime $startDateTime,
        private bool $isCurrentActivity,
        private int $timeDeltaInSeconds,
    ) {
    }

    public static function fromState(
        ActivityId $activityId,
        int $rank,
        string $name,
        Kilometer $distance,
        int $movingTimeInSeconds,
        SerializableDateTime $startDateTime,
        bool $isCurrentActivity,
        int $timeDeltaInSeconds,
    ): self {
        return new self(
            activityId: $activityId,
            rank: $rank,
            name: $name,
            distance: $distance,
            movingTimeInSeconds: $movingTimeInSeconds,
            startDateTime: $startDateTime,
            isCurrentActivity: $isCurrentActivity,
            timeDeltaInSeconds: $timeDeltaInSeconds,
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

    public function getName(): string
    {
        return $this->name;
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

    public function getStartDateTime(): SerializableDateTime
    {
        return $this->startDateTime;
    }

    public function isCurrentActivity(): bool
    {
        return $this->isCurrentActivity;
    }

    public function getTimeDeltaInSeconds(): int
    {
        return $this->timeDeltaInSeconds;
    }

    public function getTimeDeltaFormatted(): string
    {
        return ($this->timeDeltaInSeconds < 0 ? '-' : '+')
            .$this->formatDurationAsClock(abs($this->timeDeltaInSeconds));
    }
}
