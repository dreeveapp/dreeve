<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\DeleteAthleteWeight;

use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DeleteAthleteWeight extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private SerializableDateTime $on,
    ) {
    }

    public static function from(SerializableDateTime $on): self
    {
        return new self(on: $on);
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['on']) || !is_string($payload['on'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "on" date is required.');
        }

        try {
            return new self(on: SerializableDateTime::createFromFormat('!Y-m-d', trim($payload['on'])));
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The "on" date must be in YYYY-MM-DD format.');
        }
    }

    public function getOn(): SerializableDateTime
    {
        return $this->on;
    }
}
