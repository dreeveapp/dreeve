<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Connection;

use App\Infrastructure\Config\PlatformEnvironment;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class SqlitePragmasDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $wrappedDriver,
        private readonly PlatformEnvironment $environment,
    ) {
        parent::__construct($wrappedDriver);
    }

    #[\Override]
    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): DriverConnection {
        $connection = parent::connect($params);

        if (!str_contains((string) ($params['driver'] ?? ''), 'sqlite')) {
            return $connection;
        }
        if ($this->environment->isTest()) {
            return $connection;
        }

        // Lets readers and the writer run without blocking.
        $connection->exec('PRAGMA journal_mode=WAL');
        // Makes a contended statement wait up to 30s for the lock instead of failing with SQLITE_BUSY.
        // Note that SQLite ignores this when a transaction upgrades from a read to a write lock.
        $connection->exec('PRAGMA busy_timeout=30000');
        $connection->exec('PRAGMA synchronous=NORMAL');

        return $connection;
    }
}
