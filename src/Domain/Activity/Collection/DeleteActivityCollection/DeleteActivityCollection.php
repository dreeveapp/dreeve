<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\DeleteActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteActivityCollection extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private ActivityCollectionId $activityCollectionId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['activityCollectionId']) || !is_string($payload['activityCollectionId']) || '' === trim($payload['activityCollectionId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "activityCollectionId" is required.');
        }

        return new self(
            activityCollectionId: ActivityCollectionId::fromString(trim($payload['activityCollectionId'])),
        );
    }

    public function getActivityCollectionId(): ActivityCollectionId
    {
        return $this->activityCollectionId;
    }
}
