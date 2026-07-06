<?php

declare(strict_types=1);

namespace App\Domain\Settings;

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

        return is_array($data) ? $data : [];
    }

    public function save(SettingsGroup $group, array $data): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            $group->keyValueKey(),
            Value::fromString(Json::encode($data)),
        ));
    }
}
