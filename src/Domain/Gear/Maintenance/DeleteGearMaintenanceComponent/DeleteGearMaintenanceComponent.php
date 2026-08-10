<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponentId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteGearMaintenanceComponent extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private GearComponentId $gearComponentId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['gearComponentId']) || !is_string($payload['gearComponentId']) || '' === trim($payload['gearComponentId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "gearComponentId" is required.');
        }

        return new self(
            gearComponentId: GearComponentId::fromString(trim($payload['gearComponentId'])),
        );
    }

    public function getGearComponentId(): GearComponentId
    {
        return $this->gearComponentId;
    }
}
