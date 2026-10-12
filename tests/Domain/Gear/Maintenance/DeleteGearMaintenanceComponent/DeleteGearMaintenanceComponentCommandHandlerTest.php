<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent\DeleteGearMaintenanceComponent;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideGearMaintenanceConfig;
use League\Flysystem\FilesystemOperator;

class DeleteGearMaintenanceComponentCommandHandlerTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private CommandBus $commandBus;
    private FilesystemOperator $fileStorage;

    public function testItDeletesComponentAndLeavesOthersUntouched(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertCount(1, $components);
        $this->assertSame('gearComponent-di-2', $components[0]['id']);
    }

    public function testItIsANoopWhenComponentDoesNotExist(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-does-not-exist',
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertCount(2, $components);
    }

    public function testItDeletesTheComponentImageFile(): void
    {
        $this->importGearMaintenanceConfigFromArray([
            'enabled' => true,
            'components' => [[
                'id' => 'gearComponent-chain',
                'label' => 'Chain',
                'localImagePath' => 'files/gear-maintenance/old.png',
                'isRetired' => false,
                'notes' => null,
                'installations' => [['parent' => 'gear:b1', 'from' => null, 'until' => null, 'sportTypes' => []]],
                'maintenance' => [
                    ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ],
            ]],
        ]);
        $this->fileStorage->write('gear-maintenance/old.png', 'old-content');

        $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));
        $this->assertCount(0, $components);
        $this->assertFalse($this->fileStorage->fileExists('gear-maintenance/old.png'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
