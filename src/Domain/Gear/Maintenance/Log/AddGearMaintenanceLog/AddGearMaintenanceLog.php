<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log\AddGearMaintenanceLog;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class AddGearMaintenanceLog extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private GearId $gearId,
        private MaintenanceTaskId $maintenanceTaskId,
        private SerializableDateTime $performedOn,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['gearId'], $payload['maintenanceTaskId'], $payload['performedOn'])
            || !is_string($payload['gearId'])
            || !is_string($payload['maintenanceTaskId'])
            || !is_string($payload['performedOn'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "gearId", "maintenanceTaskId" and "performedOn" are required.');
        }

        if ('' === trim($payload['gearId']) || '' === trim($payload['maintenanceTaskId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('The "gearId" and "maintenanceTaskId" cannot be empty.');
        }

        try {
            $performedOn = SerializableDateTime::fromString(trim($payload['performedOn']));
        } catch (\Throwable) {
            throw CouldNotDeserializeCommand::invalidPayload('The "performedOn" is not a valid date.');
        }

        return new self(
            gearId: GearId::fromString(trim($payload['gearId'])),
            maintenanceTaskId: MaintenanceTaskId::fromString(trim($payload['maintenanceTaskId'])),
            performedOn: $performedOn,
        );
    }

    public function getGearId(): GearId
    {
        return $this->gearId;
    }

    public function getMaintenanceTaskId(): MaintenanceTaskId
    {
        return $this->maintenanceTaskId;
    }

    public function getPerformedOn(): SerializableDateTime
    {
        return $this->performedOn;
    }
}
