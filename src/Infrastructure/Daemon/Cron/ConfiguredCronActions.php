<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Cron;

use App\Domain\Import\ImportMode;
use App\Infrastructure\Config\ManagedMode;
use Cron\CronExpression;

readonly class ConfiguredCronActions
{
    private function __construct(
        /** @var list<array{action: string, expression: string, enabled: bool}> */
        private array $config,
    ) {
    }

    /**
     * @return list<CronAction>
     */
    public function forDaemon(ImportMode $importMode, ManagedMode $managedMode): array
    {
        $configByAction = array_column($this->config, null, 'action');

        $cronActions = [];
        foreach (CronActionId::cases() as $cronActionId) {
            if ($cronActionId->isForced($managedMode)) {
                $cronActions[] = CronAction::create(
                    id: $cronActionId,
                    expression: new CronExpression($cronActionId->defaultCronExpression()),
                );
                continue;
            }

            if (!$cronActionId->supports(
                importMode: $importMode,
                managedMode: $managedMode,
            )) {
                continue;
            }

            $config = $configByAction[$cronActionId->value] ?? null;
            if (null === $config || !$config['enabled']) {
                continue;
            }

            $cronActions[] = CronAction::create(
                id: $cronActionId,
                expression: new CronExpression($config['expression']),
            );
        }

        return $cronActions;
    }

    /**
     * @param array<int|string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        foreach ($config as $configuredCronAction) {
            if (!is_array($configuredCronAction)) {
                throw new InvalidCronConfig('each configured cron item needs to be an array');
            }
            foreach (['action', 'expression', 'enabled'] as $requiredKey) {
                if (array_key_exists($requiredKey, $configuredCronAction)) {
                    continue;
                }
                throw new InvalidCronConfig(sprintf('"%s" property is required', $requiredKey));
            }

            if (!is_bool($configuredCronAction['enabled'])) {
                throw new InvalidCronConfig('configuration item "enabled" must be a boolean');
            }

            if (null === CronActionId::tryFrom((string) $configuredCronAction['action'])) {
                throw new InvalidCronConfig(sprintf('"%s" is not a supported cron action', $configuredCronAction['action']));
            }

            if (!CronExpression::isValidExpression($configuredCronAction['expression'])) {
                throw new InvalidCronConfig(sprintf('"%s" is not a valid cron expression', $configuredCronAction['expression']));
            }
            if (empty($_ENV['DAEMON_DEBUG']) && !empty($configuredCronAction['enabled']) && '* * * * *' === $configuredCronAction['expression']) {
                throw new InvalidCronConfig('The cron expression "* * * * *" is not allowed as it may overload your system.');
            }
        }

        $cronActionIds = array_count_values(array_column($config, 'action'));
        if (array_keys(array_filter($cronActionIds, fn (int $count): bool => $count > 1))) {
            throw new InvalidCronConfig('each cron action can only be configured once');
        }

        return new self($config);  // @phpstan-ignore argument.type
    }
}
