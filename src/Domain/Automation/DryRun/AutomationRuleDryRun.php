<?php

declare(strict_types=1);

namespace App\Domain\Automation\DryRun;

use App\Domain\Activity\Activity;
use App\Domain\Automation\AutomationRuleId;

final readonly class AutomationRuleDryRun
{
    /**
     * @param list<RuleEvaluationResult> $ruleResults
     * @param list<AutomationRuleId>     $appliedRuleIds
     */
    public function __construct(
        private Activity $activity,
        private array $ruleResults,
        private array $appliedRuleIds,
    ) {
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    /**
     * @return list<RuleEvaluationResult>
     */
    public function getRuleResults(): array
    {
        return $this->ruleResults;
    }

    /**
     * @return list<AutomationRuleId>
     */
    public function getAppliedRuleIds(): array
    {
        return $this->appliedRuleIds;
    }

    public function hasAppliedRules(): bool
    {
        return [] !== $this->appliedRuleIds;
    }

    public function countAppliedRules(): int
    {
        return count($this->appliedRuleIds);
    }
}
