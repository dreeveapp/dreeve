<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent\UpdateGearMaintenanceComponent;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideGearMaintenanceConfig;
use League\Flysystem\FilesystemOperator;

class UpdateGearMaintenanceComponentCommandHandlerTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;
    private FilesystemOperator $fileStorage;

    public function testItUpdatesExistingComponentAndLeavesOthersUntouched(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
            'label' => 'Updated chain',
            'attachedTo' => ['b9'],
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 600, 'unit' => 'km']],
            ],
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertCount(2, $config['components']);
        $this->assertSame('gearComponent-chain', $config['components'][0]['id']);

        $chain = $this->findComponentById($config['components'], 'gearComponent-chain');
        $this->assertNotNull($chain);
        $this->assertSame('Updated chain', $chain['label']);
        $this->assertSame(['b9'], $chain['attachedTo']);

        $this->assertSame('files/gear-maintenance/chain.png', $chain['localImagePath']);
        $this->assertCount(1, $chain['maintenance']);
        $this->assertSame('maintenanceTask-chain-lubed', $chain['maintenance'][0]['id']);
        $this->assertSame(600, $chain['maintenance'][0]['interval']['value']);

        $di2 = $this->findComponentById($config['components'], 'gearComponent-di-2');
        $this->assertNotNull($di2);
        $this->assertSame('DI2 Battery', $di2['label']);
    }

    public function testItReplacesImageAndDeletesPreviousFile(): void
    {
        $this->seedComponentWithImage('files/gear-maintenance/old.png');
        $this->fileStorage->write('gear-maintenance/old.png', 'old-content');

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
            'label' => 'Chain',
            'attachedTo' => ['b1'],
            'localImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/gear-maintenance/old.png'],
                ['status' => 'new', 'filename' => 'new.png', 'content' => base64_encode('new-content')],
            ]),
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertSame('files/gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png', $config['components'][0]['localImagePath']);
        $this->assertFalse($this->fileStorage->fileExists('gear-maintenance/old.png'));
        $this->assertTrue($this->fileStorage->fileExists('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertSame('new-content', $this->fileStorage->read('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
    }

    public function testItRemovesImageAndDeletesFile(): void
    {
        $this->seedComponentWithImage('files/gear-maintenance/old.png');
        $this->fileStorage->write('gear-maintenance/old.png', 'old-content');

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
            'label' => 'Chain',
            'attachedTo' => ['b1'],
            'localImagePath' => json_encode([
                ['status' => 'removed', 'path' => '/files/gear-maintenance/old.png'],
            ]),
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertNull($config['components'][0]['localImagePath']);
        $this->assertFalse($this->fileStorage->fileExists('gear-maintenance/old.png'));
    }

    private function seedComponentWithImage(string $localImagePath): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::GEAR_MAINTENANCE,
            Value::fromString(Json::encode([
                'enabled' => true,
                'components' => [[
                    'id' => 'gearComponent-chain',
                    'label' => 'Chain',
                    'localImagePath' => $localImagePath,
                    'attachedTo' => ['b1'],
                    'maintenance' => [
                        ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                    ],
                ]],
            ])),
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $components
     *
     * @return array<string, mixed>|null
     */
    private function findComponentById(array $components, string $id): ?array
    {
        foreach ($components as $component) {
            if (($component['id'] ?? null) === $id) {
                return $component;
            }
        }

        return null;
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
