<?php

declare(strict_types=1);

namespace App\Domain\Segment\AddCustomSegment;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\ProvidesFlashMessage;
use App\Infrastructure\ValueObject\String\Name;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AddCustomSegment extends DomainCommand implements DeserializableCommand, ProvidesFlashMessage
{
    use ProvidesCommandName;

    private function __construct(
        private ActivityId $activityId,
        private int $startIndex,
        private int $endIndex,
        private Name $name,
        private bool $isFavourite,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['activityId']) || !is_string($payload['activityId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "activityId" is required.');
        }

        if (!isset($payload['name']) || !is_string($payload['name']) || '' === trim($payload['name'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "name" is required.');
        }

        foreach (['startIndex', 'endIndex'] as $key) {
            $value = $payload[$key] ?? null;
            if (!is_numeric($value) || (int) $value != $value || (int) $value < 0) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('The "%s" must be a positive whole number.', $key));
            }
        }

        if ((int) $payload['startIndex'] >= (int) $payload['endIndex']) {
            throw CouldNotDeserializeCommand::invalidPayload('The end of the segment must come after its start.');
        }

        return new self(
            activityId: ActivityId::fromString($payload['activityId']),
            startIndex: (int) $payload['startIndex'],
            endIndex: (int) $payload['endIndex'],
            name: Name::fromString(trim($payload['name'])),
            isFavourite: filter_var($payload['isFavourite'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function getEndIndex(): int
    {
        return $this->endIndex;
    }

    public function getName(): Name
    {
        return $this->name;
    }

    public function isFavourite(): bool
    {
        return $this->isFavourite;
    }

    public function getFlashMessage(TranslatorInterface $translator): string
    {
        return $translator->trans('The segment has been created. Its efforts will show up after the next import run.', [], 'admin');
    }
}
