<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Measurement\Mass\Kilogram;
use App\Infrastructure\ValueObject\Measurement\Mass\Pound;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class AthleteWeightHistory
{
    /** @var AthleteWeight[] */
    private array $weights = [];

    /**
     * @param array<int|string, mixed> $weightsFromEnv
     */
    private function __construct(
        array $weightsFromEnv,
        private readonly UnitSystem $unitSystem,
    ) {
        foreach ($weightsFromEnv as $entry) {
            $on = is_array($entry) && is_string($entry['on'] ?? null) ? $entry['on'] : '';
            $configuredWeight = is_array($entry) ? ($entry['weight'] ?? null) : null;

            if (!is_numeric($configuredWeight)) {
                throw new \InvalidArgumentException(sprintf('Invalid weight "%s" set for athlete weightHistory in config.yaml file', is_scalar($configuredWeight) ? (string) $configuredWeight : ''));
            }

            $weight = Kilogram::from(floatval($configuredWeight));
            if (UnitSystem::IMPERIAL === $this->unitSystem) {
                $weight = Pound::from(floatval($configuredWeight));
            }

            try {
                $onDate = SerializableDateTime::fromString($on);
                $this->weights[$onDate->getTimestamp()] = AthleteWeight::fromState(
                    on: $onDate,
                    weight: $weight,
                );
            } catch (\DateMalformedStringException) {
                throw new \InvalidArgumentException(sprintf('Invalid date "%s" set for athlete weightHistory in config.yaml file', $on));
            }
        }

        krsort($this->weights);
    }

    public function find(SerializableDateTime $on): AthleteWeight
    {
        $on = SerializableDateTime::fromString($on->format('Y-m-d'));
        foreach ($this->weights as $athleteWeight) {
            if ($on->isAfterOrOn($athleteWeight->getOn())) {
                return $athleteWeight;
            }
        }

        throw new EntityNotFound(sprintf('AthleteWeight for date "%s" not found', $on));
    }

    /**
     * @return AthleteWeight[]
     */
    public function findAll(): array
    {
        return $this->weights;
    }

    /**
     * @param array<int|string, mixed> $values
     */
    public static function fromArray(array $values, UnitSystem $unitSystem): self
    {
        return new self(
            weightsFromEnv: $values,
            unitSystem: $unitSystem
        );
    }
}
