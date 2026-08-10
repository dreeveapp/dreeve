<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log\UpdateGearMaintenanceLog;

use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UpdateGearMaintenanceLog extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private GearMaintenanceLogId $gearMaintenanceLogId,
        private SerializableDateTime $performedOn,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['gearMaintenanceLogId'], $payload['performedOn'])
            || !is_string($payload['gearMaintenanceLogId'])
            || !is_string($payload['performedOn'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "gearMaintenanceLogId" and "performedOn" are required.');
        }

        try {
            $performedOn = SerializableDateTime::fromString(trim($payload['performedOn']));
        } catch (\Throwable) {
            throw CouldNotDeserializeCommand::invalidPayload('The "performedOn" is not a valid date.');
        }

        return new self(
            gearMaintenanceLogId: GearMaintenanceLogId::fromString($payload['gearMaintenanceLogId']),
            performedOn: $performedOn,
        );
    }

    public function getGearMaintenanceLogId(): GearMaintenanceLogId
    {
        return $this->gearMaintenanceLogId;
    }

    public function getPerformedOn(): SerializableDateTime
    {
        return $this->performedOn;
    }
}
