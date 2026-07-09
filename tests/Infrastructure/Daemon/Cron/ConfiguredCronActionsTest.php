<?php

namespace App\Tests\Infrastructure\Daemon\Cron;

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

    public function testItYieldsEnabledActionsAsCronActions(): void
    {
        $this->assertEquals(
            [
                CronAction::create(
                    id: CronActionId::GEAR_MAINTENANCE_NOTIFICATION,
                    expression: new CronExpression('* * * * *'),
                ),
            ],
            iterator_to_array(ConfiguredCronActions::fromConfig([
                [
                    'action' => 'gearMaintenanceNotification',
                    'expression' => '* * * * *',
                    'enabled' => true,
                ],
                [
                    'action' => 'runStravaImportAndBuildApp',
                    'expression' => '* * * * *',
                    'enabled' => false,
                ],
            ]))
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

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testFromConfigItShouldThrow(array $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidCronConfig($expectedException));
        ConfiguredCronActions::fromConfig($config);
    }

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
