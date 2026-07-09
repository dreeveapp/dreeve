<?php

namespace App\Tests\Infrastructure\Daemon\Cron;

use App\Domain\Import\ImportMode;
use App\Infrastructure\Daemon\Cron\CronAction;
use App\Infrastructure\Daemon\Cron\CronActionId;
use Cron\CronExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CronActionTest extends TestCase
{
    public function testGetIdAndExpression(): void
    {
        $cronAction = CronAction::create(
            id: CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP,
            expression: new CronExpression('0 2 * * *'),
        );

        $this->assertSame(CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, $cronAction->getId());
        $this->assertEquals(new CronExpression('0 2 * * *'), $cronAction->getExpression());
    }

    #[DataProvider('provideCommands')]
    public function testGetCommand(CronActionId $id, string $expectedCommand): void
    {
        $cronAction = CronAction::create(
            id: $id,
            expression: new CronExpression('* * * * *'),
        );

        $this->assertSame($expectedCommand, $cronAction->getCommand());
    }

    public static function provideCommands(): iterable
    {
        yield 'runStravaImportAndBuildApp' => [CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, 'bin/console app:cron:run-strava-import'];
        yield 'gearMaintenanceNotification' => [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, 'bin/console app:cron:gear-maintenance-notification'];
        yield 'appUpdateAvailableNotification' => [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, 'bin/console app:cron:app-update-available-notification'];
    }

    #[DataProvider('provideImportModeSupport')]
    public function testSupportsImportMode(CronActionId $id, ImportMode $importMode, bool $expected): void
    {
        $cronAction = CronAction::create(
            id: $id,
            expression: new CronExpression('* * * * *'),
        );

        $this->assertSame($expected, $cronAction->supportsImportMode($importMode));
    }

    public static function provideImportModeSupport(): iterable
    {
        yield 'strava import is not supported in file mode' => [CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, ImportMode::FILES, false];
        yield 'strava import is supported in strava api mode' => [CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP, ImportMode::STRAVA_API, true];
        yield 'gear maintenance is supported in file mode' => [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, ImportMode::FILES, true];
        yield 'gear maintenance is supported in strava api mode' => [CronActionId::GEAR_MAINTENANCE_NOTIFICATION, ImportMode::STRAVA_API, true];
        yield 'app update is supported in file mode' => [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, ImportMode::FILES, true];
        yield 'app update is supported in strava api mode' => [CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, ImportMode::STRAVA_API, true];
    }
}
