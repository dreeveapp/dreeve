<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort\FindBestEfforts;

use App\Domain\Activity\BestEffort\ActivityBestEfforts;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class FindBestEffortsResponse implements Response
{
    public function __construct(
        private ActivityBestEfforts $bestEfforts,
        /** @var array<string, SerializableDateTime> */
        private array $startDateTimePerActivity,
    ) {
    }

    public function getBestEfforts(): ActivityBestEfforts
    {
        return $this->bestEfforts;
    }

    /**
     * @return array<string, SerializableDateTime>
     */
    public function getStartDateTimePerActivity(): array
    {
        return $this->startDateTimePerActivity;
    }
}
