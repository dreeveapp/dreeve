<?php

declare(strict_types=1);

namespace App\Domain\Challenge\Consistency\FindConsistencyMetricsPerMonth;

use App\Domain\Calendar\Month;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class FindConsistencyMetricsPerMonthResponse implements Response
{
    public function __construct(
        /** @var array<string, array{0: int, 1: Kilometer, 2: Kilometer, 3: Meter, 4: Meter, 5: Seconds, 6: int}> */
        private array $consistencyMetricsPerMonth,
    ) {
    }

    /**
     * @return array{0: int, 1: Kilometer, 2: Kilometer, 3: Meter, 4: Meter, 5: Seconds, 6: int}|null
     */
    public function getConsistencyMetricsForMonth(Month $month): ?array
    {
        if (array_key_exists($month->getId(), $this->consistencyMetricsPerMonth)) {
            return $this->consistencyMetricsPerMonth[$month->getId()];
        }

        return null;
    }
}
