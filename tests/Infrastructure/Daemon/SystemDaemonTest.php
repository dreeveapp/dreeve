<?php

namespace App\Tests\Infrastructure\Daemon;

use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Daemon\SystemDaemon;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class SystemDaemonTest extends ContainerTestCase
{
    private SystemDaemon $systemDaemon;

    public function testClearStaleImportLockRemovesLeftoverLock(): void
    {
        $this->getConnection()->executeStatement('INSERT INTO KeyValue (key, value) VALUES (:key, :value)', [
            'key' => LockName::IMPORT_DATA_OR_BUILD_APP->key(),
            'value' => Json::encode([
                'heartbeat' => 1,
                'lockAcquiredBy' => 'killed-import',
            ]),
        ]);

        $this->systemDaemon->clearStaleImportLock();

        $this->assertFalse(
            $this->getConnection()->fetchOne(
                'SELECT `value` FROM KeyValue WHERE `key` = :key',
                ['key' => LockName::IMPORT_DATA_OR_BUILD_APP->key()]
            ),
            'Stale import lock should have been cleared on daemon startup'
        );
    }

    public function testClearStaleImportLockWhenNoLockPresent(): void
    {
        $this->systemDaemon->clearStaleImportLock();

        $this->assertFalse($this->getConnection()->fetchOne(
            'SELECT `value` FROM KeyValue WHERE `key` = :key',
            ['key' => LockName::IMPORT_DATA_OR_BUILD_APP->key()]
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->systemDaemon = new SystemDaemon(
            clock: PausedClock::fromString('2025-11-01 10:00:00'),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
            importMode: ImportMode::FILES,
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString('2025-11-01 10:00:00'),
                lockName: LockName::IMPORT_DATA_OR_BUILD_APP,
            ),
        );
    }
}
