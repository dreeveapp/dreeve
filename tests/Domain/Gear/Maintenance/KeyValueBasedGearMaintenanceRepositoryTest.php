<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class KeyValueBasedGearMaintenanceRepositoryTest extends ContainerTestCase
{
    private GearMaintenanceRepository $gearMaintenanceRepository;

    public function testFindReturnsTheConfiguredConfig(): void
    {
        $this->gearMaintenanceRepository->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );

        $config = $this->gearMaintenanceRepository->find();

        $this->assertTrue($config->isFeatureEnabled());
        $this->assertTrue($config->ignoreRetiredGear());
    }

    public function testFindReturnsDisabledDefaultWhenNotConfigured(): void
    {
        $this->assertFalse($this->gearMaintenanceRepository->find()->isFeatureEnabled());
    }

    public function testUpdateConfigOnlyStoresTheSettings(): void
    {
        $this->gearMaintenanceRepository->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );

        $this->gearMaintenanceRepository->updateConfig(isFeatureEnabled: false, ignoreRetiredGear: false);

        $config = $this->gearMaintenanceRepository->find();
        $this->assertFalse($config->isFeatureEnabled());
        $this->assertFalse($config->ignoreRetiredGear());
        $this->assertSame(
            ['enabled' => false, 'ignoreRetiredGear' => false],
            Json::decode((string) $this->getContainer()->get(KeyValueStore::class)->find(Key::GEAR_MAINTENANCE)),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearMaintenanceRepository = $this->getContainer()->get(GearMaintenanceRepository::class);
    }
}
