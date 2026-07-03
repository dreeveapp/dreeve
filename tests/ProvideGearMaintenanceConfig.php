<?php

declare(strict_types=1);

namespace App\Tests;

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

    protected function importGearMaintenanceConfig(): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            key: Key::GEAR_MAINTENANCE,
            value: Value::fromString(Json::encode(Yaml::parse(<<<YML
enabled: true
ignoreRetiredGear: true
components:
  - id: gearComponent-chain
    label: Some cool chain
    localImagePath: files/gear-maintenance/chain.png
    attachedTo:
      - g1233776
      - '10130856'
      - retired
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
    attachedTo:
      - g1233776
      - g10130856
    maintenance:
      - id: maintenanceTask-di-2-charged
        label: Charge
        interval:
          value: 11
          unit: hours
gears:
  - gearId: g10130856
    imgSrc: bike.webp
YML))),
        ));
    }
}
