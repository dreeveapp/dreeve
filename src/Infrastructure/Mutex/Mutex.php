<?php

declare(strict_types=1);

namespace App\Infrastructure\Mutex;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use Doctrine\DBAL\Connection;

final class Mutex
{
    /** @var array<string, bool> */
    private static array $shutdownRegistered = [];

    private const int STALE_THRESHOLD_IN_SECONDS = 1200; // 20 minutes.
    private const string HEARTBEAT_KEY = 'heartbeat';
    private const string LOCK_ACQUIRED_BY_KEY = 'lockAcquiredBy';

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly LockName $lockName,
    ) {
    }

    public function acquireLock(string $lockAcquiredBy): void
    {
        $now = $this->clock->now()->getTimestamp();

        $this->connection->beginTransaction();

        $row = $this->connection->fetchOne(
            'SELECT `value` FROM KeyValue WHERE `key` = :key',
            ['key' => $this->lockName->key()]
        );

        if (false === $row) {
            $this->updateLockRow(
                timestamp: $now,
                lockAcquiredBy: $lockAcquiredBy)
            ;
            $this->connection->commit();

            // Register release on shutdown.
            $this->registerShutdownOnce();

            return;
        }

        $data = Json::decode($row);
        $heartbeat = $data[self::HEARTBEAT_KEY] ?? null;
        $isStale = $now - $heartbeat > self::STALE_THRESHOLD_IN_SECONDS;

        if ($isStale) {
            $this->updateLockRow(
                timestamp: $now,
                lockAcquiredBy: $lockAcquiredBy
            );
            $this->connection->commit();
            // Register release on shutdown.
            $this->registerShutdownOnce();

            return;
        }

        $this->connection->rollBack();
        throw new LockIsAlreadyAcquired(name: $this->lockName->value, lockAcquiredBy: $lockAcquiredBy);
    }

    public function heartbeat(): void
    {
        $row = $this->connection->fetchOne(
            'SELECT `value` FROM KeyValue WHERE `key` = :key',
            ['key' => $this->lockName->key()]
        );

        if (false === $row) {
            throw new \RuntimeException(sprintf('Cannot heartbeat: lock "%s" does not exist', $this->lockName->value));
        }

        $now = $this->clock->now()->getTimestamp();
        $this->updateLockRow(
            timestamp: $now,
            lockAcquiredBy: Json::decode($row)[self::LOCK_ACQUIRED_BY_KEY]
        );
    }

    public function releaseLock(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM KeyValue WHERE `key` = :key',
            ['key' => $this->lockName->key()]
        );
    }

    private function updateLockRow(int $timestamp, string $lockAcquiredBy): void
    {
        $value = Json::encode([
            self::HEARTBEAT_KEY => $timestamp,
            self::LOCK_ACQUIRED_BY_KEY => $lockAcquiredBy,
        ]);

        $this->connection->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)
             ON CONFLICT(key) DO UPDATE SET value = :value',
            ['key' => $this->lockName->key(), 'value' => $value]
        );
    }

    private function registerShutdownOnce(): void
    {
        if (isset(self::$shutdownRegistered[$this->lockName->value])) {
            return;
        }

        self::$shutdownRegistered[$this->lockName->value] = true;
        if ('test' === $_ENV['APP_ENV']) {
            return;
        }
        register_shutdown_function(fn () => $this->releaseLock()); // @codeCoverageIgnore
    }
}
