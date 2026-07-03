<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent\DeleteGearMaintenanceComponent;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideGearMaintenanceConfig;
use League\Flysystem\FilesystemOperator;

class DeleteGearMaintenanceComponentCommandHandlerTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;
    private FilesystemOperator $fileStorage;

    public function testItDeletesComponentAndLeavesOthersUntouched(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertCount(1, $config['components']);
        $this->assertSame('gearComponent-di-2', $config['components'][0]['id']);
    }

    public function testItIsANoopWhenComponentDoesNotExist(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-does-not-exist',
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertCount(2, $config['components']);
    }

    public function testItDeletesTheComponentImageFile(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::GEAR_MAINTENANCE,
            Value::fromString(Json::encode([
                'enabled' => true,
                'components' => [[
                    'id' => 'gearComponent-chain',
                    'label' => 'Chain',
                    'localImagePath' => 'files/gear-maintenance/old.png',
                    'attachedTo' => ['b1'],
                    'maintenance' => [
                        ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                    ],
                ]],
            ])),
        ));
        $this->fileStorage->write('gear-maintenance/old.png', 'old-content');

        $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));
        $this->assertCount(0, $config['components']);
        $this->assertFalse($this->fileStorage->fileExists('gear-maintenance/old.png'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
