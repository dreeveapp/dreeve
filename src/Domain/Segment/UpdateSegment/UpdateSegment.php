<?php

declare(strict_types=1);

namespace App\Domain\Segment\UpdateSegment;

use App\Domain\Segment\SegmentId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\String\Name;

final readonly class UpdateSegment extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private SegmentId $segmentId,
        private Name $name,
        private bool $isFavourite,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['segmentId']) || !is_string($payload['segmentId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "segmentId" is required.');
        }

        if (!isset($payload['name']) || !is_string($payload['name']) || '' === trim($payload['name'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "name" is required.');
        }

        return new self(
            segmentId: SegmentId::fromString($payload['segmentId']),
            name: Name::fromString(trim($payload['name'])),
            isFavourite: filter_var($payload['isFavourite'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function getSegmentId(): SegmentId
    {
        return $this->segmentId;
    }

    public function getName(): Name
    {
        return $this->name;
    }

    public function isFavourite(): bool
    {
        return $this->isFavourite;
    }
}
