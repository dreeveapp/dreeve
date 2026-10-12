<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class KeyValueBasedGearMaintenanceRepository implements GearMaintenanceRepository
{
    public function __construct(
        private KeyValueStore $keyValueStore,
        private EventBus $eventBus,
    ) {
    }

    public function find(): GearMaintenanceConfig
    {
        try {
            $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));
        } catch (EntityNotFound) {
            $config = null;
        }

        return GearMaintenanceConfig::fromArray(is_array($config) ? $config : null);
    }

    public function updateConfig(bool $isFeatureEnabled, bool $ignoreRetiredGear): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::GEAR_MAINTENANCE,
            Value::fromString(Json::encode([
                'enabled' => $isFeatureEnabled,
                'ignoreRetiredGear' => $ignoreRetiredGear,
            ])),
        ));

        $this->eventBus->publishEvents([new GearMaintenanceConfigWasUpdated()]);
    }
}
