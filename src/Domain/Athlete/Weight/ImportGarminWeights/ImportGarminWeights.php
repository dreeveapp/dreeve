<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\ImportGarminWeights;

use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ImportGarminWeights extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /** @param list<array{on: string, weightKg: float}> $weights */
    private function __construct(private array $weights)
    {
    }

    public static function fromPayload(array $payload): self
    {
        $entries = $payload['weights'] ?? null;
        if (!is_array($entries) || !array_is_list($entries) || count($entries) > 366) {
            throw CouldNotDeserializeCommand::invalidPayload('"weights" must be a list with at most 366 weigh-ins.');
        }
        $weights = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_string($entry['on'] ?? null) || !is_numeric($entry['weightKg'] ?? null)) {
                throw CouldNotDeserializeCommand::invalidPayload('Each weigh-in requires "on" and "weightKg".');
            }
            $weight = (float) $entry['weightKg'];
            if (!is_finite($weight) || $weight <= 0) {
                throw CouldNotDeserializeCommand::invalidPayload('"weightKg" must be a positive finite number.');
            }
            try {
                $date = SerializableDateTime::createFromFormat('!Y-m-d', $entry['on']);
            } catch (\InvalidArgumentException) {
                throw CouldNotDeserializeCommand::invalidPayload('"on" must be a date in YYYY-MM-DD format.');
            }
            if ($date->format('Y-m-d') !== $entry['on']) {
                throw CouldNotDeserializeCommand::invalidPayload('"on" must be a date in YYYY-MM-DD format.');
            }
            $weights[] = ['on' => $entry['on'], 'weightKg' => $weight];
        }

        return new self($weights);
    }

    /** @return list<array{on: string, weightKg: float}> */
    public function getWeights(): array
    {
        return $this->weights;
    }
}
