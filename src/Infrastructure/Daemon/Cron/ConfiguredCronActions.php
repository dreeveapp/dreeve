<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Cron;

use Cron\CronExpression;

readonly class ConfiguredCronActions implements \IteratorAggregate
{
    private function __construct(
        /** @var list<array{action: string, expression: string, enabled: bool}> */
        private array $config,
    ) {
    }

    public function getIterator(): \Traversable
    {
        $cronActions = [];
        foreach ($this->config as $action) {
            if (!$action['enabled']) {
                continue;
            }

            $cronActions[] = CronAction::create(
                id: CronActionId::from($action['action']),
                expression: new CronExpression($action['expression']),
            );
        }

        return new \ArrayIterator($cronActions);
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
