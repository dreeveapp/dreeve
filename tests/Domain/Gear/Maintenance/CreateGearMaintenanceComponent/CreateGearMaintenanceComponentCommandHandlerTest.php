<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent\CreateGearMaintenanceComponent;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideGearMaintenanceConfig;
use League\Flysystem\FilesystemOperator;

class CreateGearMaintenanceComponentCommandHandlerTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;
    private FilesystemOperator $fileStorage;

    public function testItCreatesComponentAndPreservesExistingOnes(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Brake pads',
            'installations' => [['parent' => 'gear:b1', 'from' => '2025-01-01', 'until' => '2025-03-15', 'sportTypes' => ['Ride', 'VirtualRide']], ['parent' => 'gear:g2', 'from' => '2025-03-15', 'until' => '']],
            'purchasePriceAmount' => '49.99',
            'purchasePriceCurrency' => 'EUR',
            'maintenanceTasks' => [
                ['label' => 'Replace', 'interval' => ['value' => 2000, 'unit' => 'km']],
            ],
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertCount(3, $config['components']);

        $created = $this->findComponentByLabel($config['components'], 'Brake pads');
        $this->assertNotNull($created);
        $this->assertNotEmpty($created['id']);
        $this->assertSame([
            ['parent' => 'gear:b1', 'from' => '2025-01-01', 'until' => '2025-03-15', 'sportTypes' => ['Ride', 'VirtualRide']],
            ['parent' => 'gear:g2', 'from' => '2025-03-15', 'until' => null, 'sportTypes' => []],
        ], $created['installations']);
        $this->assertNull($created['localImagePath']);
        $this->assertSame(4999, $created['purchasePrice']['amountInCents']);
        $this->assertSame('EUR', $created['purchasePrice']['currency']);
        $this->assertCount(1, $created['maintenance']);
        $this->assertSame('Replace', $created['maintenance'][0]['label']);
        $this->assertNotEmpty($created['maintenance'][0]['id']);
    }

    public function testItCreatesComponentWithImage(): void
    {
        $this->commandBus->dispatch(CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Brake pads',
            'installations' => [['parent' => 'gear:b1']],
            'localImagePath' => json_encode([
                ['status' => 'new', 'filename' => 'brakes.png', 'content' => base64_encode('image-content')],
            ]),
            'maintenanceTasks' => [
                ['label' => 'Replace', 'interval' => ['value' => 2000, 'unit' => 'km']],
            ],
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertSame('files/gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png', $config['components'][0]['localImagePath']);
        $this->assertTrue($this->fileStorage->fileExists('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertSame('image-content', $this->fileStorage->read('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
    }

    public function testItCreatesComponentWhenNoConfigExists(): void
    {
        $this->commandBus->dispatch(CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Brake pads',
            'installations' => [['parent' => 'gear:b1']],
            'maintenanceTasks' => [
                ['label' => 'Replace', 'interval' => ['value' => 2000, 'unit' => 'km']],
            ],
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertCount(1, $config['components']);
        $this->assertSame('Brake pads', $config['components'][0]['label']);
        $this->assertNull($config['components'][0]['localImagePath']);
        $this->assertArrayNotHasKey('purchasePrice', $config['components'][0]);
    }

    /**
     * @param array<int, array<string, mixed>> $components
     *
     * @return array<string, mixed>|null
     */
    private function findComponentByLabel(array $components, string $label): ?array
    {
        foreach ($components as $component) {
            if (($component['label'] ?? null) === $label) {
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
