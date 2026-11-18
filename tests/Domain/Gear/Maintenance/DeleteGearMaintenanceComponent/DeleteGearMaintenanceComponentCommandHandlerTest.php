<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent\DeleteGearMaintenanceComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent\UpdateGearMaintenanceComponent;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
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

    public function testItRejectsAComponentThatHasOtherComponentsInstalledOnIt(): void
    {
        $this->importGearMaintenanceConfig();
        $this->commandBus->dispatch(UpdateGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-di-2',
            'label' => 'DI2 Battery',
            'installations' => [['parent' => 'component:gearComponent-chain', 'until' => '2020-01-01']],
        ]));

        try {
            $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
                'gearComponentId' => 'gearComponent-chain',
            ]));
            $this->fail('Expected the deletion to be rejected');
        } catch (CouldNotProcessCommand $exception) {
            $this->assertSame('A component that has other components installed on it cannot be deleted. Retire it instead.', $exception->getMessage());
        }

        $this->assertCount(2, $this->getContainer()->get(GearComponentRepository::class)->findAll());
    }

    public function testItDeletesTheComponentImageFile(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: false,
        );
        $this->getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('b1')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                label: Name::fromString('Lube'),
                intervalValue: 500,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());
        $this->fileStorage->write('gear-maintenance/old.png', 'old-content');
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('old'))
            ->withPath(MediaItemPath::fromString('gear-maintenance/old.png'))
            ->build());

        $this->commandBus->dispatch(DeleteGearMaintenanceComponent::fromPayload([
            'gearComponentId' => 'gearComponent-chain',
        ]));

        $components = Json::decode(Json::encode($this->getContainer()->get(GearComponentRepository::class)->findAll()));
        $this->assertCount(0, $components);
        $this->assertFalse($this->fileStorage->fileExists('gear-maintenance/old.png'));
        $this->assertTrue($mediaItemRepository->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('old')]))->isEmpty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
