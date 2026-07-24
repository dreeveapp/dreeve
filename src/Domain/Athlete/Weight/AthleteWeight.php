<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight;

use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\Measurement\Mass\Weight;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class AthleteWeight
{
    private function __construct(
        private SerializableDateTime $on,
        private Weight $weight,
    ) {
    }

    public static function fromState(
        SerializableDateTime $on,
        Weight $weight,
    ): self {
        return new self(
            on: $on,
            weight: $weight,
        );
    }

    public function getOn(): SerializableDateTime
    {
        return $this->on;
    }

    public function getWeight(): Weight
    {
        return $this->weight;
    }

    public function getWeightInKg(): Kilogram
    {
        return $this->getWeight()->toKilogram();
    }
}
