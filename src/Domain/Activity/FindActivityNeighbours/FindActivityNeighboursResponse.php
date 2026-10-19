<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindActivityNeighbours;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\CQRS\Query\Response;

final readonly class FindActivityNeighboursResponse implements Response
{
    public function __construct(
        private ?ActivityId $olderActivityId,
        private ?string $olderActivityName,
        private ?ActivityId $newerActivityId,
        private ?string $newerActivityName,
    ) {
    }

    public function getOlderActivityId(): ?ActivityId
    {
        return $this->olderActivityId;
    }

    public function getOlderActivityName(): ?string
    {
        return $this->olderActivityName;
    }

    public function getNewerActivityId(): ?ActivityId
    {
        return $this->newerActivityId;
    }

    public function getNewerActivityName(): ?string
    {
        return $this->newerActivityName;
    }
}
