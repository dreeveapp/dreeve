<?php

namespace App\Tests\Infrastructure\Daemon;

use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Daemon\SystemDaemon;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class SystemDaemonTest extends ContainerTestCase
{
    private SystemDaemon $systemDaemon;

    public function testClearStaleCronLocksRemovesLeftoverLocks(): void
    {
        foreach (LockName::cases() as $lockName) {
            $this->getConnection()->executeStatement('INSERT INTO KeyValue (key, value) VALUES (:key, :value)', [
                'key' => $lockName->key(),
                'value' => Json::encode([
                    'heartbeat' => 1,
                    'lockAcquiredBy' => 'killed-cron-process',
                ]),
            ]);
        }

        $this->systemDaemon->clearStaleCronLocks();

        foreach (LockName::cases() as $lockName) {
            $this->assertFalse(
                $this->getConnection()->fetchOne(
                    'SELECT `value` FROM KeyValue WHERE `key` = :key',
                    ['key' => $lockName->key()]
                ),
                sprintf('Stale lock "%s" should have been cleared on daemon startup', $lockName->key())
            );
        }
    }

    public function testClearStaleCronLocksWhenNoLocksPresent(): void
    {
        // No leftover locks: clearing on startup must be a harmless no-op.
        $this->systemDaemon->clearStaleCronLocks();

        foreach (LockName::cases() as $lockName) {
            $this->assertFalse($this->getConnection()->fetchOne(
                'SELECT `value` FROM KeyValue WHERE `key` = :key',
                ['key' => $lockName->key()]
            ));
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->systemDaemon = new SystemDaemon(
            clock: PausedClock::fromString('2025-11-01 10:00:00'),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
            importMode: ImportMode::FILES,
            connection: $this->getConnection(),
        );
    }
}
