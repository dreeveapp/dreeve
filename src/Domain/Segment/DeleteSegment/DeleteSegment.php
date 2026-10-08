<?php

declare(strict_types=1);

namespace App\Domain\Segment\DeleteSegment;

use App\Domain\Segment\SegmentId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteSegment extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private SegmentId $segmentId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['segmentId']) || !is_string($payload['segmentId']) || '' === trim($payload['segmentId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "segmentId" is required.');
        }

        return new self(
            segmentId: SegmentId::fromString(trim($payload['segmentId'])),
        );
    }

    public function getSegmentId(): SegmentId
    {
        return $this->segmentId;
    }
}
