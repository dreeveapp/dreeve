<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\KeyValue;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\DbalKeyValueStore;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\Value;
use App\Tests\ContainerTestCase;

class DbalKeyValueStoreTest extends ContainerTestCase
{
    private DbalKeyValueStore $keyValueStore;

    public function testFind(): void
    {
        $keyValue = KeyValue::fromState(
            key: Key::THEME,
            value: Value::fromString('1989-08-14'),
        );
        $this->keyValueStore->save($keyValue);
        $this->keyValueStore->save($keyValue);

        $this->assertEquals(
            $keyValue->getValue(),
            $this->keyValueStore->find(Key::THEME)
        );
    }

    public function testClear(): void
    {
        $keyValue = KeyValue::fromState(
            key: Key::THEME,
            value: Value::fromString('1989-08-14'),
        );
        $this->keyValueStore->save($keyValue);
        $this->keyValueStore->clear(Key::THEME);

        $this->expectExceptionObject(new EntityNotFound('KeyValue "theme" not found'));
        $this->keyValueStore->find(Key::THEME);
    }

    public function testItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('KeyValue "theme" not found'));
        $this->keyValueStore->find(Key::THEME);
    }

    public function testAppendToMissingKey(): void
    {
        $this->keyValueStore->append(
            key: Key::THEME,
            value: 'descent',
        );

        $this->assertEquals(
            Value::fromString('["descent"]'),
            $this->keyValueStore->find(Key::THEME)
        );
    }

    public function testAppendToExistingList(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::THEME,
            value: Value::fromString('["descent"]'),
        ));
        $this->keyValueStore->append(
            key: Key::THEME,
            value: 'elevation',
        );

        $this->assertEquals(
            Value::fromString('["descent","elevation"]'),
            $this->keyValueStore->find(Key::THEME)
        );
    }

    public function testAppendValueAlreadyInList(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::THEME,
            value: Value::fromString('["descent","elevation"]'),
        ));
        $this->keyValueStore->append(
            key: Key::THEME,
            value: 'descent',
        );

        $this->assertEquals(
            Value::fromString('["descent","elevation"]'),
            $this->keyValueStore->find(Key::THEME)
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = new DbalKeyValueStore(
            $this->getConnection()
        );
    }
}
