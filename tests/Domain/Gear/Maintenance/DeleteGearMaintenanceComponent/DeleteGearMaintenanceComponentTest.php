<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent\DeleteGearMaintenanceComponent;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class DeleteGearMaintenanceComponentTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => '  gearComponent-chain  ',
        ]);

        $this->assertSame('gearComponent-chain', (string) $command->getGearComponentId());
    }

    public function testFromPayloadThrowsOnMissingGearComponentId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "gearComponentId" is required.'));

        DeleteGearMaintenanceComponent::fromPayload([]);
    }

    public function testFromPayloadThrowsOnEmptyGearComponentId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "gearComponentId" is required.'));

        DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => '   ',
        ]);
    }
}
