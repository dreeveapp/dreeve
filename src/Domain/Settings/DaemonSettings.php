<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\Daemon\Cron\ConfiguredCronActions;
use App\Infrastructure\Daemon\Cron\CronActionId;

final readonly class DaemonSettings
{
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

        // The cron actions are a fixed, hardcoded set (see CronActionId), so we always build the full
        // catalog, backfilling defaults for any action absent from storage.
        $config = [];
        foreach (CronActionId::cases() as $actionId) {
            $stored = is_array($storedCron[$actionId->value] ?? null) ? $storedCron[$actionId->value] : [];

            $expression = trim((string) ($stored['expression'] ?? ''));
            if ('' === $expression) {
                $expression = $actionId->defaultCronExpression();
            }

            $config[] = [
                'action' => $actionId->value,
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
