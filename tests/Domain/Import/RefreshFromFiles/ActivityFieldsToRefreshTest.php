<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\RefreshFromFiles;

use App\Domain\Import\RefreshFromFiles\ActivityFieldsToRefresh;
use App\Domain\Import\RefreshFromFiles\RefreshableActivityField;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Tests\ContainerTestCase;

class ActivityFieldsToRefreshTest extends ContainerTestCase
{
    private ActivityFieldsToRefresh $activityFieldsToRefresh;
    private KeyValueStore $keyValueStore;

    public function testAddToMissingKey(): void
    {
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);

        $this->assertEquals(
            Value::fromString('["descent"]'),
            $this->keyValueStore->find(Key::ACTIVITY_FILE_REFRESH),
        );
    }

    public function testAddToExistingList(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::ACTIVITY_FILE_REFRESH,
            value: Value::fromString('["elevation"]'),
        ));
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);

        $this->assertEquals(
            Value::fromString('["elevation","descent"]'),
            $this->keyValueStore->find(Key::ACTIVITY_FILE_REFRESH),
        );
    }

    public function testAddFieldAlreadyInList(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::ACTIVITY_FILE_REFRESH,
            value: Value::fromString('["descent","elevation"]'),
        ));
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);

        $this->assertEquals(
            Value::fromString('["descent","elevation"]'),
            $this->keyValueStore->find(Key::ACTIVITY_FILE_REFRESH),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->activityFieldsToRefresh = new ActivityFieldsToRefresh($this->keyValueStore);
    }
}
