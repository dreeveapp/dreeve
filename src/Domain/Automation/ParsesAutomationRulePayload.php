<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Condition\ConditionType;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;

trait ParsesAutomationRulePayload
{
    /**
     * @param array<string, mixed> $payload
     */
    private static function parseLabel(array $payload): string
    {
        if (!isset($payload['label']) || !is_string($payload['label']) || '' === trim($payload['label'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required.');
        }

        return trim($payload['label']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseIsEnabled(array $payload): bool
    {
        return filter_var($payload['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseStopProcessing(array $payload): bool
    {
        return filter_var($payload['stopProcessing'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{type: ConditionType, config: array<string, mixed>}>
     */
    private static function parseConditions(array $payload): array
    {
        if (!isset($payload['conditions']) || !is_array($payload['conditions']) || [] === $payload['conditions']) {
            throw CouldNotDeserializeCommand::invalidPayload('At least one condition is required.');
        }

        $conditions = [];
        foreach ($payload['conditions'] as $item) {
            $config = self::parseComponentConfig($item);
            if (!$type = ConditionType::tryFrom($item['type'])) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Invalid condition type "%s".', $item['type']));
            }
            $conditions[] = ['type' => $type, 'config' => $config];
        }

        return $conditions;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{type: ActionType, config: array<string, mixed>}>
     */
    private static function parseActions(array $payload): array
    {
        if (!isset($payload['actions']) || !is_array($payload['actions']) || [] === $payload['actions']) {
            throw CouldNotDeserializeCommand::invalidPayload('At least one action is required.');
        }

        $actions = [];
        foreach ($payload['actions'] as $item) {
            $config = self::parseComponentConfig($item);
            if (!$type = ActionType::tryFrom($item['type'])) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Invalid action type "%s".', $item['type']));
            }
            $actions[] = ['type' => $type, 'config' => $config];
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseComponentConfig(mixed $item): array
    {
        if (!is_array($item) || !isset($item['type']) || !is_string($item['type']) || '' === trim($item['type'])) {
            throw CouldNotDeserializeCommand::invalidPayload('Each component requires a non-empty "type".');
        }

        $config = $item['config'] ?? [];
        if (!is_array($config)) {
            throw CouldNotDeserializeCommand::invalidPayload('A component "config" must be an object.');
        }

        return $config;
    }
}
