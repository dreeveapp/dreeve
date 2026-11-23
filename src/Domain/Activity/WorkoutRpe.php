<?php

declare(strict_types=1);

namespace App\Domain\Activity;

final readonly class WorkoutRpe
{
    private function __construct(
        private int $value,
    ) {
    }

    public static function fromFitValue(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function toScore(): int
    {
        return (int) round($this->value / 10);
    }
}
