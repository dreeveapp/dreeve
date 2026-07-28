<?php

declare(strict_types=1);

namespace App\Domain\Automation\DryRun;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleMatcher;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Condition\ConditionEvaluationResult;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Tokenizer\Tokenizer;
use App\Infrastructure\Tokenizer\TokenizerContext;

final readonly class AutomationRuleDryRunner
{
    public function __construct(
        private AutomationRuleRepository $automationRuleRepository,
        private AutomationRuleMatcher $matcher,
        private Actions $actions,
        private Tokenizer $tokenizer,
    ) {
    }

    public function run(Activity $activity): AutomationRuleDryRun
    {
        $ruleResults = [];
        $appliedRuleIds = [];
        $stopped = false;
        $modifiedActivity = $activity;

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

            $resolvedActions = [];
            foreach ($rule->getActions() as $configuredAction) {
                $resolvedActions[] = new ConfiguredAction(
                    type: $configuredAction->getType(),
                    configuration: $this->replaceTokens(
                        configuration: $configuredAction->getConfiguration(),
                        activity: $modifiedActivity
                    ),
                );
                if (!$wasApplied) {
                    continue;
                }
                if (!$this->actions->has($configuredAction->getType())) {
                    continue;
                }
                $modifiedActivity = $this->actions->get($configuredAction->getType())
                    ->applyTo($modifiedActivity, $configuredAction->getConfiguration());
            }

            $ruleResults[] = new RuleEvaluationResult(
                label: $rule->getLabel(),
                isEnabled: $rule->isEnabled(),
                conditionResults: $conditionResults,
                configuredActions: ConfiguredActions::fromArray($resolvedActions),
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

    private function replaceTokens(RuleConfiguration $configuration, Activity $activity): RuleConfiguration
    {
        $context = TokenizerContext::empty()->with($activity);

        $replaced = [];
        foreach ($configuration->toArray() as $key => $value) {
            $replaced[$key] = is_string($value) ? $this->tokenizer->replace($value, $context) : $value;
        }

        return RuleConfiguration::fromConfig($replaced);
    }
}
