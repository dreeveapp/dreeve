<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent\CreateGearMaintenanceComponent;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use Money\Money;
use PHPUnit\Framework\TestCase;

class CreateGearMaintenanceComponentTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = CreateGearMaintenanceComponent::fromPayload([
            'label' => '  Chain  ',
            'attachedTo' => ['b1', 'g2'],
            'purchasePriceAmount' => '123.45',
            'purchasePriceCurrency' => 'EUR',
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ['label' => 'Replace', 'interval' => ['value' => 1000, 'unit' => 'km']],
            ],
        ]);

        $this->assertSame('Chain', (string) $command->getLabel());
        $this->assertNull($command->getNewImage());
        $this->assertEquals(Money::EUR(12345), $command->getPurchasePrice());
        $this->assertEquals(
            [GearId::fromUnprefixed('b1'), GearId::fromUnprefixed('g2')],
            $command->getAttachedTo()->toArray(),
        );
        $this->assertCount(2, $command->getMaintenanceTasks());
    }

    public function testFromPayloadWithNewImage(): void
    {
        $command = CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Chain',
            'attachedTo' => ['b1'],
            'localImagePath' => json_encode([
                ['status' => 'new', 'filename' => 'chain.png', 'content' => base64_encode('image-content')],
            ]),
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]);

        $this->assertNotNull($command->getNewImage());
        $this->assertSame('chain.png', (string) $command->getNewImage()->getFilename());
        $this->assertSame('image-content', $command->getNewImage()->getContent());
    }

    public function testFromPayloadThrowsOnUnsupportedImageType(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Unsupported image file type.'));

        CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Chain',
            'attachedTo' => ['b1'],
            'localImagePath' => json_encode([
                ['status' => 'new', 'filename' => 'chain.gif', 'content' => base64_encode('image-content')],
            ]),
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]);
    }

    public function testFromPayloadThrowsOnMissingLabel(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required.'));

        CreateGearMaintenanceComponent::fromPayload([
            'attachedTo' => ['b1'],
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]);
    }

    public function testFromPayloadThrowsWithoutMaintenanceTasks(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('At least one maintenance task is required.'));

        CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Chain',
            'attachedTo' => ['b1'],
            'maintenanceTasks' => [],
        ]);
    }

    public function testFromPayloadThrowsOnDuplicateMaintenanceTaskIds(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Duplicate maintenance task ids found.'));

        CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Chain',
            'attachedTo' => ['b1'],
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Replace', 'interval' => ['value' => 1000, 'unit' => 'km']],
            ],
        ]);
    }
}
