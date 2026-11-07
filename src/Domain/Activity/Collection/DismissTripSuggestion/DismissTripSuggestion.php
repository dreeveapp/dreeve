<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\DismissTripSuggestion;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DismissTripSuggestion extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private ActivityIds $activityIds,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $activityIds = $payload['activityIds'] ?? null;
        if (!is_array($activityIds) || [] === $activityIds) {
            throw CouldNotDeserializeCommand::invalidPayload('The "activityIds" must be a non-empty list.');
        }

        try {
            return new self(
                activityIds: ActivityIds::fromArray(array_map(
                    ActivityId::fromString(...),
                    array_values($activityIds),
                )),
            );
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The "activityIds" contain an invalid activity id.');
        }
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }
}
