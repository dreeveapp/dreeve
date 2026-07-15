<?php

declare(strict_types=1);

namespace App\Domain\Automation;

interface AutomationRuleRepository
{
    public function add(AutomationRule $automationRule): void;

    public function update(AutomationRule $automationRule): void;

    public function delete(AutomationRuleId $automationRuleId): void;

    public function find(AutomationRuleId $automationRuleId): AutomationRule;

    public function findAll(): AutomationRules;

    /**
     * @param list<AutomationRuleId> $orderedIds
     */
    public function updateOrder(array $orderedIds): void;
}
