<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\Eddington\Config\EddingtonConfiguration;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class KeyValueBasedSettingsRepository implements SettingsRepository
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function find(SettingsGroup $group): array
    {
        try {
            /** @var array<string, mixed>|null $data */
            $data = Json::decode((string) $this->keyValueStore->find($group->keyValueKey()));
        } catch (EntityNotFound) {
            $data = null;
        }

        return $this->applyDefaults($group, is_array($data) ? $data : []);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function applyDefaults(SettingsGroup $group, array $data): array
    {
        if (SettingsGroup::METRICS === $group && empty($data['eddington'])) {
            $data['eddington'] = EddingtonConfiguration::getDefaultConfig();
        }

        return $data;
    }

    public function save(SettingsGroup $group, array $data): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            $group->keyValueKey(),
            Value::fromString(Json::encode($data)),
        ));
    }

    public function general(): GeneralSettings
    {
        return GeneralSettings::fromArray($this->find(SettingsGroup::GENERAL));
    }

    public function appearance(): AppearanceSettings
    {
        return AppearanceSettings::fromArray($this->find(SettingsGroup::APPEARANCE));
    }

    public function import(): ImportSettings
    {
        return ImportSettings::fromArray($this->find(SettingsGroup::IMPORT));
    }

    public function metrics(): MetricsSettings
    {
        return MetricsSettings::fromArray($this->find(SettingsGroup::METRICS));
    }

    public function zwift(): ZwiftSettings
    {
        return ZwiftSettings::fromArray($this->find(SettingsGroup::ZWIFT));
    }
}
