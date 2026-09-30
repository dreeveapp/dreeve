<?php

declare(strict_types=1);

namespace App\Domain\Gear\DeleteGear;

use App\Domain\Gear\GearId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteGear extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private GearId $gearId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['gearId']) || !is_string($payload['gearId']) || '' === trim($payload['gearId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "gearId" is required.');
        }

        return new self(
            gearId: GearId::fromString(trim($payload['gearId'])),
        );
    }

    public function getGearId(): GearId
    {
        return $this->gearId;
    }
}
