<?php

declare(strict_types=1);

namespace App\Domain\Automation\DryRun;

use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\ConditionEvaluationResult;

final readonly class RuleEvaluationResult
{
    /**
     * @param list<ConditionEvaluationResult> $conditionResults
     */
    public function __construct(
        private AutomationRuleId $automationRuleId,
        private string $label,
        private bool $isEnabled,
        private array $conditionResults,
        private ConfiguredActions $configuredActions,
        private bool $allConditionsMatched,
        private bool $isWinner,
        private bool $wasEvaluated,
    ) {
    }

    public function getAutomationRuleId(): AutomationRuleId
    {
        return $this->automationRuleId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @return list<ConditionEvaluationResult>
     */
    public function getConditionResults(): array
    {
        return $this->conditionResults;
    }

    public function getConfiguredActions(): ConfiguredActions
    {
        return $this->configuredActions;
    }

    public function allConditionsMatched(): bool
    {
        return $this->allConditionsMatched;
    }

    public function isWinner(): bool
    {
        return $this->isWinner;
    }

    public function wasEvaluated(): bool
    {
        return $this->wasEvaluated;
    }
}
