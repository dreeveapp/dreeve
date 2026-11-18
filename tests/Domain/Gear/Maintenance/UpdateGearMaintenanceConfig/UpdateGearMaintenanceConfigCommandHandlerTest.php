<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\UpdateGearMaintenanceConfig;

use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\UpdateGearMaintenanceConfig\UpdateGearMaintenanceConfig;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;

class UpdateGearMaintenanceConfigCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testItUpdatesSettingsAndPreservesComponents(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->build());
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('di-2'))
            ->withLabel('DI2 Battery')
            ->build());

        $this->commandBus->dispatch(UpdateGearMaintenanceConfig::fromPayload([
            'enabled' => 'false',
            'ignoreRetiredGear' => 'false',
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertFalse($config['enabled']);
        $this->assertFalse($config['ignoreRetiredGear']);
        $this->assertCount(2, $gearComponentRepository->findAll());
    }

    public function testItStoresSettingsWhenNoConfigExists(): void
    {
        $this->commandBus->dispatch(UpdateGearMaintenanceConfig::fromPayload([
            'enabled' => '1',
            'ignoreRetiredGear' => '1',
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));

        $this->assertTrue($config['enabled']);
        $this->assertTrue($config['ignoreRetiredGear']);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
    }
}
