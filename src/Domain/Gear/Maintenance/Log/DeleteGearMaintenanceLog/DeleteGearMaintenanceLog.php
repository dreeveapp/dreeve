<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log\DeleteGearMaintenanceLog;

use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteGearMaintenanceLog extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private GearMaintenanceLogId $gearMaintenanceLogId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['gearMaintenanceLogId']) || !is_string($payload['gearMaintenanceLogId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "gearMaintenanceLogId" is required.');
        }

        return new self(
            gearMaintenanceLogId: GearMaintenanceLogId::fromString($payload['gearMaintenanceLogId']),
        );
    }

    public function getGearMaintenanceLogId(): GearMaintenanceLogId
    {
        return $this->gearMaintenanceLogId;
    }
}
