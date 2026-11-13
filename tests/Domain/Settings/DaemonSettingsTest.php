<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Import\ImportMode;
use App\Domain\Settings\DaemonSettings;
use App\Infrastructure\Config\ManagedMode;
use App\Infrastructure\Daemon\Cron\CronAction;
use App\Infrastructure\Daemon\Cron\CronActionId;
use App\Infrastructure\Daemon\Cron\InvalidCronConfig;
use PHPUnit\Framework\TestCase;

class DaemonSettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV['DAEMON_DEBUG'] = 1;
    }

    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = DaemonSettings::fromArray([]);
        $this->assertSame([], $settings->getConfiguredCronActions()->forDaemon(
            importMode: ImportMode::STRAVA_API,
            managedMode: ManagedMode::fromString('0'),
        ));
    }

    public function testItOnlyYieldsEnabledActions(): void
    {
        $settings = DaemonSettings::fromArray([
            'cron' => [
                'runStravaImportAndBuildApp' => ['expression' => '0 3 * * *', 'enabled' => true],
                'gearMaintenanceNotification' => ['expression' => '0 4 * * *', 'enabled' => false],
            ],
        ]);

        $this->assertEquals(
            [
                CronAction::create(
                    id: CronActionId::RUN_STRAVA_IMPORT,
                    expression: new \Cron\CronExpression('0 3 * * *'),
                ),
            ],
            $settings->getConfiguredCronActions()->forDaemon(
                importMode: ImportMode::STRAVA_API,
                managedMode: ManagedMode::fromString('0'),
            )
        );
    }

    public function testItCoercesStoredStringBooleans(): void
    {
        $settings = DaemonSettings::fromArray([
            'cron' => [
                'appUpdateAvailableNotification' => ['expression' => '0 5 * * *', 'enabled' => '1'],
            ],
        ]);

        $actions = $settings->getConfiguredCronActions()->forDaemon(
            importMode: ImportMode::STRAVA_API,
            managedMode: ManagedMode::fromString('0'),
        );
        $this->assertCount(1, $actions);
        $this->assertSame(CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION, $actions[0]->getId());
    }

    public function testItFallsBackToTheDefaultExpressionWhenNoneStored(): void
    {
        $settings = DaemonSettings::fromArray([
            'cron' => [
                'runStravaImportAndBuildApp' => ['enabled' => true],
            ],
        ]);

        $actions = $settings->getConfiguredCronActions()->forDaemon(
            importMode: ImportMode::STRAVA_API,
            managedMode: ManagedMode::fromString('0'),
        );
        $this->assertCount(1, $actions);
        $this->assertSame('30 2 * * *', (string) $actions[0]->getExpression());
    }

    public function testItThrowsForAnInvalidCronExpression(): void
    {
        $this->expectExceptionObject(new InvalidCronConfig('"not-a-cron" is not a valid cron expression'));

        DaemonSettings::fromArray([
            'cron' => [
                'runStravaImportAndBuildApp' => ['expression' => 'not-a-cron', 'enabled' => true],
            ],
        ]);
    }
}
