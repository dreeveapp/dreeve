<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Condition\ConditionEvaluationResult;
use App\Domain\Automation\Condition\Conditions;

final readonly class AutomationRuleMatcher
{
    public function __construct(
        private Conditions $conditions,
    ) {
    }

    public function firstMatching(AutomationRules $rules, Activity $activity): ?AutomationRule
    {
        foreach ($rules as $rule) {
            if ($rule->isEnabled() && $this->conditionsMatch($rule, $activity)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @return list<ConditionEvaluationResult>
     */
    public function evaluateConditions(AutomationRule $rule, Activity $activity): array
    {
        $conditionResults = [];
        foreach ($rule->getConditions() as $configuredCondition) {
            $type = $configuredCondition->getType();
            if (!$this->conditions->has($type)) {
                continue;
            }

            $conditionResults[] = new ConditionEvaluationResult(
                type: $type,
                configuration: $configuredCondition->getConfiguration(),
                matched: $this->conditions->get($type)->matches($activity, $configuredCondition->getConfiguration()),
            );
        }

        return $conditionResults;
    }

    private function conditionsMatch(AutomationRule $rule, Activity $activity): bool
    {
        $matchedAtLeastOne = false;
        foreach ($rule->getConditions() as $configuredCondition) {
            if (!$this->conditions->has($configuredCondition->getType())) {
                continue;
            }
            if (!$this->conditions->get($configuredCondition->getType())
                ->matches($activity, $configuredCondition->getConfiguration())) {
                return false;
            }
            $matchedAtLeastOne = true;
        }

        return $matchedAtLeastOne;
    }
}
