<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\DaemonSettings;
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
        $this->assertSame([], iterator_to_array($settings->getConfiguredCronActions()));
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
                    id: CronActionId::RUN_STRAVA_IMPORT_AND_BUILD_APP,
                    expression: new \Cron\CronExpression('0 3 * * *'),
                ),
            ],
            iterator_to_array($settings->getConfiguredCronActions())
        );
    }

    public function testItCoercesStoredStringBooleans(): void
    {
        $settings = DaemonSettings::fromArray([
            'cron' => [
                'appUpdateAvailableNotification' => ['expression' => '0 5 * * *', 'enabled' => '1'],
            ],
        ]);

        $actions = iterator_to_array($settings->getConfiguredCronActions());
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

        $actions = iterator_to_array($settings->getConfiguredCronActions());
        $this->assertCount(1, $actions);
        $this->assertSame('0 2 * * *', (string) $actions[0]->getExpression());
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
