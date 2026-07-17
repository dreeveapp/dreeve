<?php

declare(strict_types=1);

namespace App\Domain\Automation\DryRun;

use App\Domain\Activity\Activity;
use App\Domain\Automation\AutomationRuleId;
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
        $rules = $this->automationRuleRepository->findAll();
        $winningRuleId = $this->matcher->firstMatching(
            rules: $rules,
            activity: $activity
        )?->getId();

        $ruleResults = [];
        $reachedWinner = false;

        foreach ($rules as $rule) {
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

            $isWinner = $winningRuleId instanceof AutomationRuleId && (string) $rule->getId() === (string) $winningRuleId;
            $wasEvaluated = !$reachedWinner;
            if ($isWinner) {
                $reachedWinner = true;
            }

            $ruleResults[] = new RuleEvaluationResult(
                automationRuleId: $rule->getId(),
                label: $rule->getLabel(),
                isEnabled: $rule->isEnabled(),
                conditionResults: $conditionResults,
                configuredActions: $rule->getActions(),
                allConditionsMatched: $allConditionsMatched,
                isWinner: $isWinner,
                wasEvaluated: $wasEvaluated,
            );
        }

        return new AutomationRuleDryRun(
            activity: $activity,
            ruleResults: $ruleResults,
            winningRuleId: $winningRuleId
        );
    }
}
