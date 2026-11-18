<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent\UpdateGearMaintenanceComponent;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItems;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Media\MediaItemBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateGearMaintenanceComponentCommandHandlerTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private CommandBus $commandBus;
    private FilesystemOperator $fileStorage;

    public function testItUpdatesExistingComponentAndLeavesOthersUntouched(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
            'label' => 'Updated chain',
            'installations' => [['parent' => 'gear:gear-b9']],
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 600, 'unit' => 'km']],
            ],
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertCount(2, $components);

        $chain = $this->findComponentById($components, 'gearComponent-chain');
        $this->assertNotNull($chain);
        $this->assertSame('Updated chain', $chain['label']);
        $this->assertSame([
            ['parent' => 'gear:gear-b9', 'from' => null, 'until' => null, 'sportTypes' => []],
        ], $chain['installations']);

        $this->assertSame('mediaItem-chain', $chain['mediaItemId']);
        $this->assertCount(1, $chain['maintenance']);
        $this->assertSame('maintenanceTask-chain-lubed', $chain['maintenance'][0]['id']);
        $this->assertSame(600, $chain['maintenance'][0]['interval']['value']);

        $di2 = $this->findComponentById($components, 'gearComponent-di-2');
        $this->assertNotNull($di2);
        $this->assertSame('DI2 Battery', $di2['label']);
    }

    public function testItRemovesAllMaintenanceTasks(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
            'label' => 'Chain',
            'installations' => [['parent' => 'gear:gear-b9']],
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $chain = $this->findComponentById($components, 'gearComponent-chain');
        $this->assertNotNull($chain);
        $this->assertSame([], $chain['maintenance']);
    }

    public function testItRejectsAnUnknownComponent(): void
    {
        $this->expectExceptionObject(CouldNotProcessCommand::withReason('The component does not exist.'));

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-does-not-exist',
            'label' => 'Chain',
            'installations' => [['parent' => 'gear:gear-b1']],
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]));
    }

    public function testItInstallsAComponentOnAnotherComponent(): void
    {
        $this->importGearMaintenanceConfig();

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-di-2',
            'label' => 'DI2 Battery',
            'installations' => [['parent' => 'component:gearComponent-chain']],
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-di-2-charged', 'label' => 'Charge', 'interval' => ['value' => 11, 'unit' => 'hours']],
            ],
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));
        $di2 = $this->findComponentById($components, 'gearComponent-di-2');
        $this->assertNotNull($di2);
        $this->assertSame(
            [['parent' => 'component:gearComponent-chain', 'from' => null, 'until' => null, 'sportTypes' => []]],
            $di2['installations'],
        );
    }

    /**
     * @param list<array<string, mixed>> $chainInstallations
     */
    #[DataProvider('provideInstallationLoops')]
    public function testItRejectsInstallationLoops(array $chainInstallations): void
    {
        $this->importGearMaintenanceConfig();
        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-di-2',
            'label' => 'DI2 Battery',
            'installations' => [['parent' => 'component:gearComponent-chain']],
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-di-2-charged', 'label' => 'Charge', 'interval' => ['value' => 11, 'unit' => 'hours']],
            ],
        ]));
        $componentsBefore = Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll());

        try {
            $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
                'gearComponentId' => 'gearComponent-chain',
                'label' => 'Some cool chain',
                'installations' => $chainInstallations,
                'maintenanceTasks' => [
                    ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ],
            ]));
            $this->fail('Expected the installation loop to be rejected');
        } catch (CouldNotProcessCommand $exception) {
            $this->assertSame('A component cannot be installed on itself or on one of its own parts.', $exception->getMessage());
        }

        $this->assertSame($componentsBefore, Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function provideInstallationLoops(): iterable
    {
        yield 'installed on itself' => [[['parent' => 'component:gearComponent-chain']]];
        yield 'installed on one of its own parts' => [[['parent' => 'gear:gear-b1'], ['parent' => 'component:gearComponent-di-2']]];
    }

    public function testItReplacesImageAndDeletesPreviousFile(): void
    {
        $this->seedComponentWithImage('mediaItem-old');
        $this->fileStorage->write('gear-maintenance/old.png', 'old-content');
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
            ->withPath(MediaItemPath::fromString('gear-maintenance/old.png'))
            ->build());

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
            'label' => 'Chain',
            'installations' => [['parent' => 'gear:gear-b1']],
            'localImagePath' => json_encode([
                ['status' => 'removed', 'id' => 'mediaItem-old'],
                ['status' => 'new', 'filename' => 'new.png', 'content' => base64_encode('new-content')],
            ]),
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertSame('mediaItem-0025176c-5652-11ee-923d-02424dd627d5', $components[0]['mediaItemId']);
        $this->assertFalse($this->fileStorage->fileExists('gear-maintenance/old.png'));
        $this->assertTrue($this->fileStorage->fileExists('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertSame('new-content', $this->fileStorage->read('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertEquals(
            MediaItems::fromArray([
                MediaItemBuilder::fromDefaults()
                    ->withMediaItemId(MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'))
                    ->withPath(MediaItemPath::fromString('gear-maintenance/0025176c-5652-11ee-923d-02424dd627d5.png'))
                    ->build(),
            ]),
            $mediaItemRepository->findByIds(MediaItemIds::fromArray([
                MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'),
                MediaItemId::fromUnprefixed('old'),
            ])),
        );
    }

    public function testItRemovesImageAndDeletesFile(): void
    {
        $this->seedComponentWithImage('mediaItem-old');
        $this->fileStorage->write('gear-maintenance/old.png', 'old-content');
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
            ->withPath(MediaItemPath::fromString('gear-maintenance/old.png'))
            ->build());

        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
            'label' => 'Chain',
            'installations' => [['parent' => 'gear:gear-b1']],
            'localImagePath' => json_encode([
                ['status' => 'removed', 'id' => 'mediaItem-old'],
            ]),
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));

        $this->assertNull($components[0]['mediaItemId']);
        $this->assertFalse($this->fileStorage->fileExists('gear-maintenance/old.png'));
        $this->assertTrue($mediaItemRepository->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('old')]))->isEmpty());
    }

    private function seedComponentWithImage(string $mediaItemId): void
    {
        $this->importGearMaintenanceConfigFromArray([
            'enabled' => true,
            'components' => [[
                'id' => 'gearComponent-chain',
                'label' => 'Chain',
                'mediaItemId' => $mediaItemId,
                'isRetired' => false,
                'notes' => null,
                'installations' => [['parent' => 'gear:gear-b1', 'from' => null, 'until' => null, 'sportTypes' => []]],
                'maintenance' => [
                    ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ],
            ]],
        ]);
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
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
