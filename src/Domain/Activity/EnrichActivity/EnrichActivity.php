<?php

declare(strict_types=1);

namespace App\Domain\Activity\EnrichActivity;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\ProvidesFlashMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EnrichActivity extends DomainCommand implements DeserializableCommand, ProvidesFlashMessage
{
    use ProvidesCommandName;

    public function __construct(
        private ActivityId $activityId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['activityId']) || !is_string($payload['activityId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "activityId" is required.');
        }

        return new self(
            activityId: ActivityId::fromString($payload['activityId']),
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getFlashMessage(TranslatorInterface $translator): string
    {
        return $translator->trans('Weather and reverse geocoding have been retrieved.', [], 'admin');
    }
}
