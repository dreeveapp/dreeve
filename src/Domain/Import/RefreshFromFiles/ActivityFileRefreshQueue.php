<?php

declare(strict_types=1);

namespace App\Domain\Import\RefreshFromFiles;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;

final readonly class ActivityFileRefreshQueue
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function isQueued(): bool
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
