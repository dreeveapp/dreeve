<?php

declare(strict_types=1);

namespace App\Domain\Automation\DryRun;

use App\Domain\Activity\Activity;
use App\Domain\Automation\AutomationRuleId;

final readonly class AutomationRuleDryRun
{
    /**
     * @param list<RuleEvaluationResult> $ruleResults
     */
    public function __construct(
        private Activity $activity,
        private array $ruleResults,
        private ?AutomationRuleId $winningRuleId,
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

    public function getWinningRuleId(): ?AutomationRuleId
    {
        return $this->winningRuleId;
    }

    public function hasWinner(): bool
    {
        return $this->winningRuleId instanceof AutomationRuleId;
    }
}
