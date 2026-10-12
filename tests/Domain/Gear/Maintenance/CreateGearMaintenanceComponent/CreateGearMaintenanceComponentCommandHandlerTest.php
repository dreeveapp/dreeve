<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent\CreateGearMaintenanceComponent;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideGearMaintenanceConfig;
use League\Flysystem\FilesystemOperator;

class CreateGearMaintenanceComponentCommandHandlerTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private CommandBus $commandBus;
    private FilesystemOperator $fileStorage;

    public function testItCreatesComponentAndPreservesExistingOnes(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(CreateGearMaintenanceComponent::fromPayload([
            'label' => 'Brake pads',
            'notes' => ' Shimano B05S ',
            'installations' => [['parent' => 'gear:b1', 'from' => '2025-01-01', 'until' => '2025-03-15', 'sportTypes' => ['Ride', 'VirtualRide']], ['parent' => 'gear:g2', 'from' => '2025-03-15', 'until' => '']],
            'purchasePriceAmount' => '49.99',
            'purchasePriceCurrency' => 'EUR',
            'maintenanceTasks' => [
                ['label' => 'Replace', 'interval' => ['value' => 2000, 'unit' => 'km']],
            ],
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertCount(3, $components);

        $created = $this->findComponentByLabel($components, 'Brake pads');
        $this->assertNotNull($created);
        $this->assertNotEmpty($created['id']);
        $this->assertSame([
            ['parent' => 'gear:b1', 'from' => '2025-01-01', 'until' => '2025-03-15', 'sportTypes' => ['Ride', 'VirtualRide']],
            ['parent' => 'gear:g2', 'from' => '2025-03-15', 'until' => null, 'sportTypes' => []],
        ], $created['installations']);
        $this->assertNull($created['localImagePath']);
        $this->assertFalse($created['isRetired']);
        $this->assertSame('Shimano B05S', $created['notes']);
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

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertSame('files/gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png', $components[0]['localImagePath']);
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

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertCount(1, $components);
        $this->assertSame('Brake pads', $components[0]['label']);
        $this->assertNull($components[0]['localImagePath']);
        $this->assertArrayNotHasKey('purchasePrice', $components[0]);
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
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
