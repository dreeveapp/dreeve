<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Daemon\Cron;

use App\Domain\Import\ImportMode;
use App\Infrastructure\Config\ManagedMode;
use App\Infrastructure\Daemon\Cron\CronActionId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CronActionIdTest extends TestCase
{
    #[DataProvider('provideCommands')]
    public function testCommand(CronActionId $id, string $expectedCommand): void
    {
        $this->assertSame($expectedCommand, $id->command());
    }

    /**
     * @return iterable<int, array{CronActionId, string}>
     */
    public static function provideCommands(): iterable
    {
        yield [CronActionId::RUN_STRAVA_IMPORT, 'bin/console app:import:strava'];
        yield [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, 'bin/console app:notification:gear-maintenance'];
        yield [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, 'bin/console app:notification:app-update-available'];
        yield [CronActionId::BACKUP, 'bin/console app:backup:create'];
    }

    #[DataProvider('provideDefaultCronExpressions')]
    public function testDefaultCronExpression(CronActionId $id, string $expected): void
    {
        $this->assertSame($expected, $id->defaultCronExpression());
    }

    /**
     * @return iterable<int, array{CronActionId, string}>
     */
    public static function provideDefaultCronExpressions(): iterable
    {
        yield [CronActionId::RUN_STRAVA_IMPORT, '30 2 * * *'];
        yield [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, '0 4 * * *'];
        yield [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, '0 4 * * *'];
        yield [CronActionId::BACKUP, '0 3 * * *'];
    }

    #[DataProvider('provideSupports')]
    public function testSupports(CronActionId $id, ImportMode $importMode, ManagedMode $managedMode, bool $expected): void
    {
        $this->assertSame($expected, $id->supports(
            importMode: $importMode,
            managedMode: $managedMode,
        ));
    }

    /**
     * @return iterable<string, array{CronActionId, ImportMode, ManagedMode, bool}>
     */
    public static function provideSupports(): iterable
    {
        yield 'strava import not supported in file mode' => [CronActionId::RUN_STRAVA_IMPORT, ImportMode::FILES, ManagedMode::fromString('0'), false];
        yield 'strava import supported in strava api mode' => [CronActionId::RUN_STRAVA_IMPORT, ImportMode::STRAVA_API, ManagedMode::fromString('0'), true];
        yield 'strava import supported on managed instances' => [CronActionId::RUN_STRAVA_IMPORT, ImportMode::STRAVA_API, ManagedMode::fromString('1'), true];
        yield 'gear maintenance supported in file mode' => [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, ImportMode::FILES, ManagedMode::fromString('0'), true];
        yield 'gear maintenance supported in strava api mode' => [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, ImportMode::STRAVA_API, ManagedMode::fromString('0'), true];
        yield 'gear maintenance supported on managed instances' => [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, ImportMode::STRAVA_API, ManagedMode::fromString('1'), true];
        yield 'app update supported in file mode' => [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, ImportMode::FILES, ManagedMode::fromString('0'), true];
        yield 'app update supported in strava api mode' => [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, ImportMode::STRAVA_API, ManagedMode::fromString('0'), true];
        yield 'app update not supported on managed instances' => [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, ImportMode::STRAVA_API, ManagedMode::fromString('1'), false];
        yield 'backup supported in file mode' => [CronActionId::BACKUP, ImportMode::FILES, ManagedMode::fromString('0'), true];
        yield 'backup supported in strava api mode' => [CronActionId::BACKUP, ImportMode::STRAVA_API, ManagedMode::fromString('0'), true];
        yield 'backup supported on managed instances' => [CronActionId::BACKUP, ImportMode::STRAVA_API, ManagedMode::fromString('1'), true];
    }
}
