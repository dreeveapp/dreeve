<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

final readonly class HeartRateZoneTotals
{
    /**
     * @param array<string, int>                $total
     * @param array<string, array<string, int>> $perActivityType            keyed by ActivityType->value
     * @param array<string, int>                $inLastXDays
     * @param array<string, int>                $inLastXDaysAsOfPreviousDay
     */
    private function __construct(
        private array $total,
        private array $perActivityType,
        private array $inLastXDays,
        private array $inLastXDaysAsOfPreviousDay,
    ) {
    }

    /**
     * @param array<string, int>                $total
     * @param array<string, array<string, int>> $perActivityType
     * @param array<string, int>                $inLastXDays
     * @param array<string, int>                $inLastXDaysAsOfPreviousDay
     */
    public static function fromState(
        array $total,
        array $perActivityType,
        array $inLastXDays,
        array $inLastXDaysAsOfPreviousDay,
    ): self {
        return new self(
            total: $total,
            perActivityType: $perActivityType,
            inLastXDays: $inLastXDays,
            inLastXDaysAsOfPreviousDay: $inLastXDaysAsOfPreviousDay,
        );
    }

    /**
     * @return array<string, int>
     */
    public function getTotal(): array
    {
        return $this->total;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getPerActivityType(): array
    {
        return $this->perActivityType;
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
