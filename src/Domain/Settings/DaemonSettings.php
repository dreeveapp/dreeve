<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\Daemon\Cron\ConfiguredCronActions;

final readonly class DaemonSettings
{
    /**
     * @var array<string, string>
     */
    public const array CRON_ACTIONS = [
        'importDataAndBuildApp' => '0 2 * * *',
        'gearMaintenanceNotification' => '* * * * *',
        'appUpdateAvailableNotification' => '* * * * *',
    ];

    private function __construct(
        private ConfiguredCronActions $configuredCronActions,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];
        $storedCron = is_array($data['cron'] ?? null) ? $data['cron'] : [];

        $config = [];
        foreach (self::CRON_ACTIONS as $action => $defaultExpression) {
            $stored = is_array($storedCron[$action] ?? null) ? $storedCron[$action] : [];

            $expression = trim((string) ($stored['expression'] ?? ''));
            if ('' === $expression) {
                $expression = $defaultExpression;
            }

            $config[] = [
                'action' => $action,
                'expression' => $expression,
                'enabled' => filter_var($stored['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return new self(
            configuredCronActions: ConfiguredCronActions::fromConfig($config),
        );
    }

    public function getConfiguredCronActions(): ConfiguredCronActions
    {
        return $this->configuredCronActions;
    }
}
