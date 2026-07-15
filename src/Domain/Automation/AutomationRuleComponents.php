<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;

final readonly class AutomationRuleComponents
{
    public function __construct(
        private Conditions $conditions,
        private Actions $actions,
    ) {
    }

    /**
     * @param list<array{type: ConditionType, config: array<string, mixed>}> $rawConditions
     *
     * @throws InvalidAutomationRule
     */
    public function buildConditions(array $rawConditions): ConfiguredConditions
    {
        $configured = ConfiguredConditions::empty();
        foreach ($rawConditions as $raw) {
            $condition = $this->conditions->get($raw['type']);
            $configuration = $this->coerce($condition->getDefaultConfiguration(), $raw['config']);
            $condition->guardValidConfiguration($configuration);

            $configured->add(new ConfiguredCondition($raw['type'], $configuration));
        }

        return $configured;
    }

    /**
     * @param list<array{type: ActionType, config: array<string, mixed>}> $rawActions
     *
     * @throws InvalidAutomationRule
     */
    public function buildActions(array $rawActions): ConfiguredActions
    {
        $configured = ConfiguredActions::empty();
        foreach ($rawActions as $raw) {
            $action = $this->actions->get($raw['type']);
            $configuration = $this->coerce($action->getDefaultConfiguration(), $raw['config']);
            $action->guardValidConfiguration($configuration);

            $configured->add(new ConfiguredAction($raw['type'], $configuration));
        }

        return $configured;
    }

    /**
     * @param array<string, mixed> $submitted
     */
    private function coerce(RuleConfiguration $defaults, array $submitted): RuleConfiguration
    {
        $configuration = RuleConfiguration::empty();

        foreach ($defaults->toArray() as $key => $default) {
            $configuration->add($key, match (true) {
                is_bool($default) => filter_var($submitted[$key] ?? false, FILTER_VALIDATE_BOOLEAN),
                is_int($default) => array_key_exists($key, $submitted) ? (int) $submitted[$key] : $default,
                is_float($default) => array_key_exists($key, $submitted) ? (float) $submitted[$key] : $default,
                is_array($default) => (array) ($submitted[$key] ?? []),
                is_string($default) => array_key_exists($key, $submitted) ? (string) $submitted[$key] : $default,
                // Null default: treat as a nullable string, empty means "unset".
                default => '' === trim((string) ($submitted[$key] ?? '')) ? null : (string) $submitted[$key],
            });
        }

        return $configuration;
    }
}
