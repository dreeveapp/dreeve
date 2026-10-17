<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

final readonly class HeartRateZoneTotals
{
    /**
     * @param array<string, int> $inLastXDays
     * @param array<string, int> $inLastXDaysAsOfPreviousDay
     */
    private function __construct(
        private array $inLastXDays,
        private array $inLastXDaysAsOfPreviousDay,
    ) {
    }

    /**
     * @param array<string, int> $inLastXDays
     * @param array<string, int> $inLastXDaysAsOfPreviousDay
     */
    public static function fromState(
        array $inLastXDays,
        array $inLastXDaysAsOfPreviousDay,
    ): self {
        return new self(
            inLastXDays: $inLastXDays,
            inLastXDaysAsOfPreviousDay: $inLastXDaysAsOfPreviousDay,
        );
    }

    /**
     * @return array<string, int>
     */
    public function getInLastXDays(): array
    {
        return $this->inLastXDays;
    }

    /**
     * @return array<string, int>
     */
    public function getInLastXDaysAsOfPreviousDay(): array
    {
        return $this->inLastXDaysAsOfPreviousDay;
    }
}
