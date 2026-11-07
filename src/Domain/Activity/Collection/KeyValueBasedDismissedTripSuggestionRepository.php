<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class KeyValueBasedDismissedTripSuggestionRepository implements DismissedTripSuggestionRepository
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function findActivityIds(): ActivityIds
    {
        try {
            $activityIds = Json::decode((string) $this->keyValueStore->find(Key::DISMISSED_TRIP_SUGGESTIONS));
        } catch (EntityNotFound) {
            return ActivityIds::empty();
        }

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            is_array($activityIds) ? $activityIds : [],
        ));
    }

    public function add(ActivityIds $activityIds): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::DISMISSED_TRIP_SUGGESTIONS,
            value: Value::fromString(Json::encode($this->findActivityIds()->mergeWith($activityIds)->unique())),
        ));
    }
}
