<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\AddActivityToCollection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityReference;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class AddActivityToCollection extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private ActivityCollectionId $activityCollectionId,
        private ActivityId $activityId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['activityCollectionId'], $payload['activityId'])
            || !is_string($payload['activityCollectionId'])
            || !is_string($payload['activityId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "activityCollectionId" and "activityId" are required.');
        }

        try {
            $activityId = ActivityReference::parse($payload['activityId']);
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The "activityId" does not contain a valid activity id.');
        }

        return new self(
            activityCollectionId: ActivityCollectionId::fromString($payload['activityCollectionId']),
            activityId: $activityId,
        );
    }

    public function getActivityCollectionId(): ActivityCollectionId
    {
        return $this->activityCollectionId;
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }
}
