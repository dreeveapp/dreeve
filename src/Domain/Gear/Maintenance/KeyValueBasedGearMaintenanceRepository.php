<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
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
        private GearIdRepository $gearIdRepository,
    ) {
    }

    public function find(): GearMaintenanceConfig
    {
        try {
            /** @var array<string, mixed>|null $config */
            $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));
        } catch (EntityNotFound) {
            $config = null;
        }

        $gearMaintenanceConfig = GearMaintenanceConfig::fromArray(is_array($config) ? $config : null);

        // The config references gear ids that may be unprefixed (copy-pasted from a gear URL),
        // while the database stores them with their Strava "b"/"g" prefix. Normalize them here
        // so every consumer works with ids that match the database.
        $gearMaintenanceConfig->normalizeGearIds($this->gearIdRepository->findAll());

        return $gearMaintenanceConfig;
    }

    public function findMaintenanceTask(MaintenanceTaskId $maintenanceTaskId): ?MaintenanceTask
    {
        foreach ($this->find()->getGearComponents() as $gearComponent) {
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                if ($maintenanceTask->getId() == $maintenanceTaskId) {
                    return $maintenanceTask;
                }
            }
        }

        return null;
    }

    public function findComponentForMaintenanceTask(MaintenanceTaskId $maintenanceTaskId): ?GearComponent
    {
        foreach ($this->find()->getGearComponents() as $gearComponent) {
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                if ($maintenanceTask->getId() == $maintenanceTaskId) {
                    return $gearComponent;
                }
            }
        }

        return null;
    }

    public function findComponent(GearComponentId $gearComponentId): ?GearComponent
    {
        foreach ($this->find()->getGearComponents() as $gearComponent) {
            if ((string) $gearComponent->getId() === (string) $gearComponentId) {
                return $gearComponent;
            }
        }

        return null;
    }

    public function updateConfig(bool $isFeatureEnabled, bool $ignoreRetiredGear): void
    {
        try {
            $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));
        } catch (EntityNotFound) {
            $config = [];
        }
        if (!is_array($config)) {
            $config = [];
        }

        $config['enabled'] = $isFeatureEnabled;
        $config['ignoreRetiredGear'] = $ignoreRetiredGear;

        $this->save($config);
    }

    public function saveComponent(GearComponent $gearComponent): void
    {
        $config = $this->readConfig();

        $components = is_array($config['components'] ?? null) ? $config['components'] : [];
        $index = array_find_key(
            $components,
            static fn (array $component): bool => ($component['id'] ?? null) === (string) $gearComponent->getId(),
        );

        if (null === $index) {
            $components[] = $gearComponent;
        } else {
            $components[$index] = $gearComponent;
        }

        $config['components'] = array_values($components);

        $this->save($config);
    }

    public function deleteComponent(GearComponentId $gearComponentId): void
    {
        $config = $this->readConfig();

        $components = is_array($config['components'] ?? null) ? $config['components'] : [];
        $config['components'] = array_values(array_filter(
            $components,
            static fn (array $component): bool => ($component['id'] ?? null) !== (string) $gearComponentId,
        ));

        $this->save($config);
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(): array
    {
        try {
            $config = Json::decode((string) $this->keyValueStore->find(Key::GEAR_MAINTENANCE));
        } catch (EntityNotFound) {
            $config = [];
        }
        if (!is_array($config)) {
            $config = [];
        }

        $config['enabled'] ??= false;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function save(array $config): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::GEAR_MAINTENANCE,
            Value::fromString(Json::encode($config)),
        ));
    }
}
