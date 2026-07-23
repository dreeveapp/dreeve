<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DbalAutomationRuleRepository extends DbalRepository implements AutomationRuleRepository
{
    public function add(AutomationRule $automationRule): void
    {
        $sql = 'INSERT INTO AutomationRule (automationRuleId, label, isEnabled, stopProcessing, sortOrder, conditions, actions, createdOn)
                VALUES (:automationRuleId, :label, :isEnabled, :stopProcessing, :sortOrder, :conditions, :actions, :createdOn)';

        $this->connection->executeStatement($sql, [
            'automationRuleId' => $automationRule->getId(),
            'label' => $automationRule->getLabel(),
            'isEnabled' => (int) $automationRule->isEnabled(),
            'stopProcessing' => (int) $automationRule->stopProcessing(),
            'sortOrder' => $automationRule->getSortOrder(),
            'conditions' => Json::encode($automationRule->getConditions()),
            'actions' => Json::encode($automationRule->getActions()),
            'createdOn' => $automationRule->getCreatedOn(),
        ]);
    }

    public function update(AutomationRule $automationRule): void
    {
        $sql = 'UPDATE AutomationRule SET
                    label = :label,
                    isEnabled = :isEnabled,
                    stopProcessing = :stopProcessing,
                    sortOrder = :sortOrder,
                    conditions = :conditions,
                    actions = :actions
                WHERE automationRuleId = :automationRuleId';

        $this->connection->executeStatement($sql, [
            'automationRuleId' => $automationRule->getId(),
            'label' => $automationRule->getLabel(),
            'isEnabled' => (int) $automationRule->isEnabled(),
            'stopProcessing' => (int) $automationRule->stopProcessing(),
            'sortOrder' => $automationRule->getSortOrder(),
            'conditions' => Json::encode($automationRule->getConditions()),
            'actions' => Json::encode($automationRule->getActions()),
        ]);
    }

    public function delete(AutomationRuleId $automationRuleId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM AutomationRule WHERE automationRuleId = :automationRuleId',
            ['automationRuleId' => (string) $automationRuleId],
        );
    }

    public function find(AutomationRuleId $automationRuleId): AutomationRule
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM AutomationRule WHERE automationRuleId = :automationRuleId',
            ['automationRuleId' => $automationRuleId],
        )->fetchAssociative();

        if (false === $result) {
            throw new EntityNotFound(sprintf('AutomationRule "%s" not found', $automationRuleId));
        }

        return $this->hydrate($result);
    }

    public function findAll(): AutomationRules
    {
        $results = $this->connection->executeQuery(
            'SELECT * FROM AutomationRule ORDER BY sortOrder ASC'
        )->fetchAllAssociative();

        return AutomationRules::fromArray(array_map(
            $this->hydrate(...),
            $results,
        ));
    }

    public function updateOrder(array $orderedIds): void
    {
        $sql = 'UPDATE AutomationRule SET sortOrder = :sortOrder WHERE automationRuleId = :automationRuleId';

        foreach ($orderedIds as $sortOrder => $automationRuleId) {
            $this->connection->executeStatement($sql, [
                'sortOrder' => $sortOrder,
                'automationRuleId' => (string) $automationRuleId,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): AutomationRule
    {
        return AutomationRule::fromState(
            automationRuleId: AutomationRuleId::fromString($result['automationRuleId']),
            label: (string) $result['label'],
            isEnabled: (bool) $result['isEnabled'],
            stopProcessing: (bool) $result['stopProcessing'],
            sortOrder: (int) $result['sortOrder'],
            conditions: $this->hydrateConditions($result['conditions']),
            actions: $this->hydrateActions($result['actions']),
            createdOn: SerializableDateTime::fromString($result['createdOn']),
        );
    }

    private function hydrateConditions(string $json): ConfiguredConditions
    {
        /** @var list<array{type: string, config: array<string, mixed>}> $components */
        $components = Json::decode($json);

        $conditions = ConfiguredConditions::empty();
        foreach ($components as $component) {
            if (!$type = ConditionType::tryFrom($component['type'])) {
                continue;
            }
            $conditions->add(new ConfiguredCondition($type, RuleConfiguration::fromConfig($component['config'])));
        }

        return $conditions;
    }

    private function hydrateActions(string $json): ConfiguredActions
    {
        /** @var list<array{type: string, config: array<string, mixed>}> $components */
        $components = Json::decode($json);

        $actions = ConfiguredActions::empty();
        foreach ($components as $component) {
            if (!$type = ActionType::tryFrom($component['type'])) {
                continue;
            }
            $actions->add(new ConfiguredAction($type, RuleConfiguration::fromConfig($component['config'])));
        }

        return $actions;
    }
}
