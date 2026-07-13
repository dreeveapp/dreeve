<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Connection;

use App\Infrastructure\Config\PlatformEnvironment;
use App\Infrastructure\Doctrine\Connection\SqlitePragmasDriver;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use PHPUnit\Framework\TestCase;

class SqlitePragmasDriverTest extends TestCase
{
    public function testItAppliesPragmasForSqliteConnection(): void
    {
        $connection = $this->createMock(DriverConnection::class);

        $executed = [];
        $connection
            ->expects($this->exactly(3))
            ->method('exec')
            ->willReturnCallback(function (string $sql) use (&$executed): int {
                $executed[] = $sql;

                return 0;
            });

        $wrappedDriver = $this->createMock(Driver::class);
        $wrappedDriver
            ->expects($this->once())
            ->method('connect')
            ->willReturn($connection);

        $driver = new SqlitePragmasDriver($wrappedDriver, PlatformEnvironment::PROD);
        $result = $driver->connect(['driver' => 'pdo_sqlite']);

        $this->assertSame($connection, $result);
        $this->assertSame([
            'PRAGMA journal_mode=WAL',
            'PRAGMA busy_timeout=30000',
            'PRAGMA synchronous=NORMAL',
        ], $executed);
    }

    public function testItDoesNotApplyPragmasForNonSqliteConnection(): void
    {
        $connection = $this->createMock(DriverConnection::class);
        $connection
            ->expects($this->never())
            ->method('exec');

        $wrappedDriver = $this->createMock(Driver::class);
        $wrappedDriver
            ->expects($this->once())
            ->method('connect')
            ->willReturn($connection);

        $driver = new SqlitePragmasDriver($wrappedDriver, PlatformEnvironment::PROD);
        $result = $driver->connect(['driver' => 'pdo_pgsql']);

        $this->assertSame($connection, $result);
    }

    public function testItDoesNotApplyPragmasInTestEnvironment(): void
    {
        $connection = $this->createMock(DriverConnection::class);
        $connection
            ->expects($this->never())
            ->method('exec');

        $wrappedDriver = $this->createMock(Driver::class);
        $wrappedDriver
            ->expects($this->once())
            ->method('connect')
            ->willReturn($connection);

        $driver = new SqlitePragmasDriver($wrappedDriver, PlatformEnvironment::TEST);
        $result = $driver->connect(['driver' => 'pdo_sqlite']);

        $this->assertSame($connection, $result);
    }
}
