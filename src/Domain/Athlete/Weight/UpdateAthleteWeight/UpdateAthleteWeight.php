<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\UpdateAthleteWeight;

use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UpdateAthleteWeight extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

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

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['on']) || !is_string($payload['on'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "on" date is required.');
        }

        try {
            $on = SerializableDateTime::createFromFormat('!Y-m-d', trim($payload['on']));
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The "on" date must be in YYYY-MM-DD format.');
        }

        $weight = $payload['weight'] ?? null;
        if (!is_numeric($weight) || (float) $weight <= 0) {
            throw CouldNotDeserializeCommand::invalidPayload('The "weight" must be a positive number.');
        }

        return new self(
            on: $on,
            weight: (float) $weight,
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
