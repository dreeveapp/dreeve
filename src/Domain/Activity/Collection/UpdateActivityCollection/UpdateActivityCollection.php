<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class UpdateActivityCollection extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private ActivityCollectionId $activityCollectionId,
        private string $name,
        private ?string $description,
        private ActivityCollectionType $type,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['activityCollectionId'], $payload['name'])
            || !is_string($payload['activityCollectionId'])
            || !is_string($payload['name'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "activityCollectionId" and "name" are required.');
        }

        $name = trim($payload['name']);
        if ('' === $name) {
            throw CouldNotDeserializeCommand::invalidPayload('The name cannot be empty.');
        }

        $type = $payload['type'] ?? null;
        if (!is_string($type) || !$activityCollectionType = ActivityCollectionType::tryFrom($type)) {
            throw CouldNotDeserializeCommand::invalidPayload('The type is invalid.');
        }

        $description = $payload['description'] ?? null;
        $description = is_string($description) ? trim($description) : '';

        return new self(
            activityCollectionId: ActivityCollectionId::fromString($payload['activityCollectionId']),
            name: $name,
            description: '' === $description ? null : $description,
            type: $activityCollectionType,
        );
    }

    public function getActivityCollectionId(): ActivityCollectionId
    {
        return $this->activityCollectionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): ActivityCollectionType
    {
        return $this->type;
    }
}
