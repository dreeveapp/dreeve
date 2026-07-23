<?php

declare(strict_types=1);

namespace App\Domain\Automation\DryRun;

use App\Domain\Activity\Activity;
use App\Domain\Automation\AutomationRuleMatcher;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Condition\ConditionEvaluationResult;

final readonly class AutomationRuleDryRunner
{
    public function __construct(
        private AutomationRuleRepository $automationRuleRepository,
        private AutomationRuleMatcher $matcher,
    ) {
    }

    public function run(Activity $activity): AutomationRuleDryRun
    {
        $ruleResults = [];
        $appliedRuleIds = [];
        $stopped = false;

        foreach ($this->automationRuleRepository->findAll() as $rule) {
            $conditionResults = $this->matcher->evaluateConditions(
                rule: $rule,
                activity: $activity
            );

            $allConditionsMatched = [] !== $conditionResults
                && array_reduce(
                    $conditionResults,
                    static fn (bool $carry, ConditionEvaluationResult $result): bool => $carry && $result->isMatched(),
                    true,
                );

            $wasEvaluated = !$stopped;
            $wasApplied = $wasEvaluated && $rule->isEnabled() && $this->matcher->matches($rule, $activity);
            $stoppedProcessing = $wasApplied && $rule->stopProcessing();

            if ($wasApplied) {
                $appliedRuleIds[] = $rule->getId();
            }
            if ($stoppedProcessing) {
                $stopped = true;
            }

            $ruleResults[] = new RuleEvaluationResult(
                label: $rule->getLabel(),
                isEnabled: $rule->isEnabled(),
                conditionResults: $conditionResults,
                configuredActions: $rule->getActions(),
                allConditionsMatched: $allConditionsMatched,
                wasApplied: $wasApplied,
                stoppedProcessing: $stoppedProcessing,
                wasEvaluated: $wasEvaluated,
            );
        }

        return new AutomationRuleDryRun(
            activity: $activity,
            ruleResults: $ruleResults,
            appliedRuleIds: $appliedRuleIds
        );
    }
}
