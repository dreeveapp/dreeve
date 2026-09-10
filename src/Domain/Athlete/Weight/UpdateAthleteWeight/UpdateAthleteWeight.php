<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\UpdateAthleteWeight;

use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UpdateAthleteWeight extends DomainCommand
{
    private function __construct(
        private SerializableDateTime $on,
        private float $weight,
    ) {
    }

    public static function from(SerializableDateTime $on, float $weight): self
    {
        return new self(
            on: $on,
            weight: $weight,
        );
    }

    public function getOn(): SerializableDateTime
    {
        return $this->on;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }
}
