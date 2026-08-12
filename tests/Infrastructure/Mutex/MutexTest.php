<?php

namespace App\Tests\Infrastructure\Mutex;

use App\Infrastructure\Mutex\LockIsAlreadyAcquired;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class MutexTest extends ContainerTestCase
{
    private Mutex $mutex;

    public function testAcquireLockWhenNewLock(): void
    {
        $this->mutex->acquireLock('myProcess');
        $this->mutex->releaseLock();
        $this->mutex->acquireLock('myProcess');

        $this->expectExceptionObject(new LockIsAlreadyAcquired(
            name: LockName::IMPORT_DATA->value,
            lockAcquiredBy: 'myProcess',
        ));

        $this->mutex->acquireLock('myProcess');
    }

    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testAcquireLockWhenLockIsStale(): void
    {
        $this->getConnection()->executeStatement('INSERT INTO KeyValue (key, value) VALUES (:key, :value)', [
            'key' => 'lock.importData',
            'value' => Json::encode([
                'heartbeat' => 1,
                'lockAcquiredBy' => 'myProcess',
            ]),
        ]);

        $this->mutex->acquireLock('myProcess');
    }

    public function testHeartBeat(): void
    {
        $this->getConnection()->executeStatement('INSERT INTO KeyValue (key, value) VALUES (:key, :value)', [
            'key' => 'lock.importData',
            'value' => Json::encode([
                'heartbeat' => 1,
                'lockAcquiredBy' => 'myProcess',
            ]),
        ]);

        $this->mutex->heartbeat();

        $this->expectExceptionObject(new LockIsAlreadyAcquired(
            name: LockName::IMPORT_DATA->value,
            lockAcquiredBy: 'myProcess',
        ));

        $this->mutex->acquireLock('myProcess');
    }

    public function testHeartBeatWithUnexistingLock(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Cannot heartbeat: lock "importData" does not exist'));

        $this->mutex->heartbeat();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mutex = new Mutex(
            connection: $this->getConnection(),
            clock: PausedClock::fromString('2025-11-01 10:00:00'),
            lockName: LockName::IMPORT_DATA,
        );
    }
}
