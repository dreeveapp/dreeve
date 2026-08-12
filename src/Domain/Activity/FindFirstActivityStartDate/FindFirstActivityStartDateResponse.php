<?php

declare(strict_types=1);

namespace App\Domain\Activity\FindFirstActivityStartDate;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class FindFirstActivityStartDateResponse implements Response
{
    public function __construct(
        private ?SerializableDateTime $startDate,
    ) {
    }

    public function getStartDate(): SerializableDateTime
    {
        return $this->startDate ?? throw new \RuntimeException('No activities found');
    }
}
