<?php

namespace App\Tests\Infrastructure\Daemon\Cron;

use App\Domain\Import\ImportMode;
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

    public static function provideCommands(): iterable
    {
        yield [CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, 'bin/console app:cron:run-strava-import'];
        yield [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, 'bin/console app:cron:gear-maintenance-notification'];
        yield [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, 'bin/console app:cron:app-update-available-notification'];
    }

    #[DataProvider('provideDefaultCronExpressions')]
    public function testDefaultCronExpression(CronActionId $id, string $expected): void
    {
        $this->assertSame($expected, $id->defaultCronExpression());
    }

    public static function provideDefaultCronExpressions(): iterable
    {
        yield [CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, '0 2 * * *'];
        yield [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, '0 4 * * *'];
        yield [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, '0 4 * * *'];
    }

    #[DataProvider('provideImportModeSupport')]
    public function testSupportsImportMode(CronActionId $id, ImportMode $importMode, bool $expected): void
    {
        $this->assertSame($expected, $id->supportsImportMode($importMode));
    }

    public static function provideImportModeSupport(): iterable
    {
        yield 'strava import not supported in file mode' => [CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, ImportMode::FILES, false];
        yield 'strava import supported in strava api mode' => [CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, ImportMode::STRAVA_API, true];
        yield 'gear maintenance supported in file mode' => [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, ImportMode::FILES, true];
        yield 'app update supported in file mode' => [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, ImportMode::FILES, true];
    }
}
