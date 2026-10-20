<?php

declare(strict_types=1);

namespace App\Domain\Activity\DeleteActivities;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteActivities extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    public function __construct(
        private ActivityIds $activityIds,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $activityIds = $payload['activityIds'] ?? null;
        if (!is_array($activityIds) || [] === $activityIds || !array_is_list($activityIds)) {
            throw CouldNotDeserializeCommand::invalidPayload('A non-empty "activityIds" list is required.');
        }

        foreach ($activityIds as $activityId) {
            if (!is_string($activityId) || '' === trim($activityId)) {
                throw CouldNotDeserializeCommand::invalidPayload('Each "activityIds" entry must be a non-empty string.');
            }
        }

        return new self(ActivityIds::fromArray(array_map(
            static fn (string $activityId): ActivityId => ActivityId::fromString(trim($activityId)),
            $activityIds,
        )));
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }
}
