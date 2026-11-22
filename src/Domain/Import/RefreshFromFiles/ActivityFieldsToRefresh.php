<?php

declare(strict_types=1);

namespace App\Domain\Import\RefreshFromFiles;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class ActivityFieldsToRefresh
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function add(RefreshableActivityField $field): void
    {
        try {
            $fields = Json::decode((string) $this->keyValueStore->find(Key::ACTIVITY_FILE_REFRESH));
        } catch (EntityNotFound) {
            $fields = [];
        }

        if (in_array($field->value, $fields, true)) {
            return;
        }

        $fields[] = $field->value;
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::ACTIVITY_FILE_REFRESH,
            value: Value::fromString(Json::encode($fields)),
        ));
    }

    public function hasAny(): bool
    {
        try {
            $this->keyValueStore->find(Key::ACTIVITY_FILE_REFRESH);
        } catch (EntityNotFound) {
            return false;
        }

        return true;
    }

    /**
     * @return list<RefreshableActivityField>
     */
    public function find(): array
    {
        return array_values(array_filter(array_map(
            RefreshableActivityField::tryFrom(...),
            Json::decode((string) $this->keyValueStore->find(Key::ACTIVITY_FILE_REFRESH)),
        )));
    }

    public function clear(): void
    {
        $this->keyValueStore->clear(Key::ACTIVITY_FILE_REFRESH);
    }
}
