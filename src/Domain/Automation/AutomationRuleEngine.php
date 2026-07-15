<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Condition\Conditions;

final readonly class AutomationRuleEngine
{
    public function __construct(
        private AutomationRuleRepository $automationRuleRepository,
        private Conditions $conditions,
        private Actions $actions,
    ) {
    }

    public function apply(Activity $activity): Activity
    {
        foreach ($this->automationRuleRepository->findAll() as $rule) {
            if (!$rule->isEnabled()) {
                continue;
            }
            if (!$this->allConditionsMatch($rule, $activity)) {
                continue;
            }
            foreach ($rule->getActions() as $configuredAction) {
                if (!$this->actions->has($configuredAction->getType())) {
                    continue;
                }
                $activity = $this->actions->get($configuredAction->getType())
                    ->applyTo($activity, $configuredAction->getConfiguration());
            }

            return $activity;
        }

        return $activity;
    }

    private function allConditionsMatch(AutomationRule $rule, Activity $activity): bool
    {
        $conditions = $rule->getConditions();
        if ($conditions->isEmpty()) {
            return false;
        }

        foreach ($conditions as $configuredCondition) {
            if (!$this->conditions->has($configuredCondition->getType())) {
                return false;
            }
            if (!$this->conditions->get($configuredCondition->getType())
                ->matches($activity, $configuredCondition->getConfiguration())) {
                return false;
            }
        }

        return true;
    }
}
