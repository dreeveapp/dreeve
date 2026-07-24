<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Context;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Mile;

final readonly class EddingtonContext implements MilestoneContext
{
    public function __construct(
        private string $label,
        private int $number,
        private Kilometer|Mile $distance,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getDistance(): Mile|Kilometer
    {
        return $this->distance;
    }
}
