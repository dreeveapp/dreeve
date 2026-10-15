<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ExplorerTileHistoryEntry
{
    public function __construct(
        private ActivityId $activityId,
        private SerializableDateTime $startDateTime,
        private int $maxSquareSize,
        private int $maxClusterSize,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getStartDateTime(): SerializableDateTime
    {
        return $this->startDateTime;
    }

    public function getMaxSquareSize(): int
    {
        return $this->maxSquareSize;
    }

    public function getMaxClusterSize(): int
    {
        return $this->maxClusterSize;
    }
}
