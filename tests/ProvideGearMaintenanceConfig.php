<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Yaml\Yaml;

trait ProvideGearMaintenanceConfig
{
    abstract protected static function getContainer(): Container;

    /**
     * @param array<string, mixed> $config
     */
    protected function importGearMaintenanceConfigFromArray(array $config): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            key: Key::GEAR_MAINTENANCE,
            value: Value::fromString(Json::encode([
                'enabled' => $config['enabled'] ?? false,
                'ignoreRetiredGear' => $config['ignoreRetiredGear'] ?? false,
            ])),
        ));

        /** @var GearComponentRepository $gearComponentRepository */
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
        foreach ($config['components'] ?? [] as $component) {
            $gearComponentRepository->add(GearComponent::fromArray($component));
        }
    }

    protected function importGearMaintenanceConfig(): void
    {
        $this->importGearMaintenanceConfigFromArray(Yaml::parse(<<<YML
enabled: true
ignoreRetiredGear: true
components:
  - id: gearComponent-chain
    label: Some cool chain
    localImagePath: files/gear-maintenance/chain.png
    isRetired: false
    notes: null
    installations:
      - { parent: 'gear:g1233776', from: null, until: null, sportTypes: [] }
      - { parent: 'gear:10130856', from: null, until: null, sportTypes: [] }
      - { parent: 'gear:retired', from: null, until: null, sportTypes: [] }
    purchasePrice:
      amountInCents: 123456
      currency: 'EUR'
    maintenance:
      - id: maintenanceTask-chain-lubed
        label: Lube
        interval:
          value: 500
          unit: km
      - id: maintenanceTask-chain-replaced
        label: Replace
        interval:
          value: 1000
          unit: km
      - id: maintenanceTask-chain-cleaned
        label: Clean
        interval:
          value: 1000
          unit: km
  - id: gearComponent-di-2
    label: DI2 Battery
    localImagePath: files/gear-maintenance/battery.png
    isRetired: false
    notes: null
    installations:
      - { parent: 'gear:g1233776', from: null, until: null, sportTypes: [] }
      - { parent: 'gear:g10130856', from: null, until: null, sportTypes: [] }
    maintenance:
      - id: maintenanceTask-di-2-charged
        label: Charge
        interval:
          value: 11
          unit: hours
YML));
    }
}
