<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class AutomationRulesBackfillQueue
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function queue(AutomationRulesBackfillRequest $request): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::AUTOMATION_RULES_BACKFILL,
            value: Value::fromString(Json::encode($request)),
        ));
    }

    public function isQueued(): bool
    {
        try {
            $this->keyValueStore->find(Key::AUTOMATION_RULES_BACKFILL);
        } catch (EntityNotFound) {
            return false;
        }

        return true;
    }

    public function find(): AutomationRulesBackfillRequest
    {
        return AutomationRulesBackfillRequest::fromArray(
            Json::decode((string) $this->keyValueStore->find(Key::AUTOMATION_RULES_BACKFILL))
        );
    }

    public function clear(): void
    {
        $this->keyValueStore->clear(Key::AUTOMATION_RULES_BACKFILL);
    }
}
