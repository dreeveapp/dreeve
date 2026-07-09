<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\DaemonSettings;
use App\Infrastructure\Daemon\Cron\CronAction;
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
                'importDataAndBuildApp' => ['expression' => '0 3 * * *', 'enabled' => true],
                'gearMaintenanceNotification' => ['expression' => '0 4 * * *', 'enabled' => false],
            ],
        ]);

        $this->assertEquals(
            [
                CronAction::create(
                    id: 'importDataAndBuildApp',
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
        $this->assertSame('appUpdateAvailableNotification', $actions[0]->getId());
    }

    public function testItFallsBackToTheDefaultExpressionWhenNoneStored(): void
    {
        $settings = DaemonSettings::fromArray([
            'cron' => [
                'importDataAndBuildApp' => ['enabled' => true],
            ],
        ]);

        $actions = iterator_to_array($settings->getConfiguredCronActions());
        $this->assertCount(1, $actions);
        $this->assertSame('0 2 * * *', (string) $actions[0]->getExpression());
    }

    public function testItThrowsForAnInvalidCronExpression(): void
    {
        $this->expectException(InvalidCronConfig::class);

        DaemonSettings::fromArray([
            'cron' => [
                'importDataAndBuildApp' => ['expression' => 'not-a-cron', 'enabled' => true],
            ],
        ]);
    }
}
