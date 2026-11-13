<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Daemon\Cron;

use App\Domain\Import\ImportMode;
use App\Infrastructure\Config\ManagedMode;
use App\Infrastructure\Daemon\Cron\ConfiguredCronActions;
use App\Infrastructure\Daemon\Cron\CronAction;
use App\Infrastructure\Daemon\Cron\CronActionId;
use App\Infrastructure\Daemon\Cron\InvalidCronConfig;
use Cron\CronExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfiguredCronActionsTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV['DAEMON_DEBUG'] = 1;
    }

    public function testForDaemon(): void
    {
        $configuredCronActions = ConfiguredCronActions::fromConfig([
            [
                'action' => 'runStravaImportAndBuildApp',
                'expression' => '0 1 * * *',
                'enabled' => true,
            ],
            [
                'action' => 'gearMaintenanceNotification',
                'expression' => '0 2 * * *',
                'enabled' => true,
            ],
            [
                'action' => 'appUpdateAvailableNotification',
                'expression' => '0 5 * * *',
                'enabled' => true,
            ],
            [
                'action' => 'backup',
                'expression' => '0 6 * * *',
                'enabled' => false,
            ],
        ]);

        $this->assertEquals(
            [
                CronAction::create(
                    id: CronActionId::GEAR_MAINTENANCE_NOTIFICATION,
                    expression: new CronExpression('0 2 * * *'),
                ),
                CronAction::create(
                    id: CronActionId::APP_UPDATE_AVAILABLE_NOTIFICATION,
                    expression: new CronExpression('0 5 * * *'),
                ),
            ],
            $configuredCronActions->forDaemon(
                importMode: ImportMode::FILES,
                managedMode: ManagedMode::fromString('0'),
            )
        );

        $this->assertEquals(
            [
                CronAction::create(
                    id: CronActionId::RUN_STRAVA_IMPORT,
                    expression: new CronExpression('0 1 * * *'),
                ),
                CronAction::create(
                    id: CronActionId::GEAR_MAINTENANCE_NOTIFICATION,
                    expression: new CronExpression('0 2 * * *'),
                ),
                CronAction::create(
                    id: CronActionId::BACKUP,
                    expression: new CronExpression('0 3 * * *'),
                ),
            ],
            $configuredCronActions->forDaemon(
                importMode: ImportMode::STRAVA_API,
                managedMode: ManagedMode::fromString('1'),
            )
        );
    }

    public function testCronExpressionEveryMinute(): void
    {
        $_ENV['DAEMON_DEBUG'] = 0;

        $this->expectExceptionObject(new InvalidCronConfig('The cron expression "* * * * *" is not allowed as it may overload your system.'));
        ConfiguredCronActions::fromConfig([
            [
                'action' => 'gearMaintenanceNotification',
                'expression' => '* * * * *',
                'enabled' => true,
            ],
        ]);
    }

    /**
     * @param list<mixed> $config
     */
    #[DataProvider('provideInvalidConfig')]
    public function testFromConfigItShouldThrow(array $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidCronConfig($expectedException));
        ConfiguredCronActions::fromConfig($config);
    }

    /**
     * @return iterable<string, array{list<mixed>, string}>
     */
    public static function provideInvalidConfig(): iterable
    {
        yield 'config is not an array' => [['lol'], 'each configured cron item needs to be an array'];

        $config = self::getValidConfig();
        unset($config[0]['action']);
        yield 'missing key "action"' => [$config, '"action" property is required'];

        $config = self::getValidConfig();
        unset($config[0]['expression']);
        yield 'missing key "expression"' => [$config, '"expression" property is required'];

        $config = self::getValidConfig();
        unset($config[0]['enabled']);
        yield 'missing key "enabled"' => [$config, '"enabled" property is required'];

        $config = self::getValidConfig();
        $config[0]['action'] = 'notARealAction';
        yield 'unsupported cron action' => [$config, '"notARealAction" is not a supported cron action'];

        $config = self::getValidConfig();
        $config[0]['expression'] = 'lol';
        yield 'invalid cron expression' => [$config, '"lol" is not a valid cron expression'];

        $config = self::getValidConfig();
        $config[1]['action'] = 'gearMaintenanceNotification';
        yield 'duplicate cron action' => [$config, 'each cron action can only be configured once'];

        $config = self::getValidConfig();
        $config[0]['enabled'] = 'lol';
        yield 'invalid enabled value' => [$config, 'configuration item "enabled" must be a boolean'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function getValidConfig(): array
    {
        return [
            [
                'action' => 'gearMaintenanceNotification',
                'expression' => '* * * * *',
                'enabled' => true,
            ],
            [
                'action' => 'appUpdateAvailableNotification',
                'expression' => '* * * * *',
                'enabled' => false,
            ],
        ];
    }
}
