<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRule;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class AutomationRuleBuilder
{
    private AutomationRuleId $automationRuleId;
    private string $label = 'My rule';
    private bool $isEnabled = true;
    private bool $stopProcessing = true;
    private int $sortOrder = 0;
    private ConfiguredConditions $conditions;
    private ConfiguredActions $actions;
    private SerializableDateTime $createdOn;

    private function __construct()
    {
        $this->automationRuleId = AutomationRuleId::fromUnprefixed('1');
        $this->conditions = ConfiguredConditions::empty();
        $this->actions = ConfiguredActions::empty();
        $this->createdOn = SerializableDateTime::fromString('2023-10-17 16:15:04');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): AutomationRule
    {
        return AutomationRule::create(
            automationRuleId: $this->automationRuleId,
            label: $this->label,
            isEnabled: $this->isEnabled,
            stopProcessing: $this->stopProcessing,
            sortOrder: $this->sortOrder,
            conditions: $this->conditions,
            actions: $this->actions,
            createdOn: $this->createdOn,
        );
    }

    public function withAutomationRuleId(AutomationRuleId $automationRuleId): self
    {
        $this->automationRuleId = $automationRuleId;

        return $this;
    }

    public function withLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function withIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function withStopProcessing(bool $stopProcessing): self
    {
        $this->stopProcessing = $stopProcessing;

        return $this;
    }

    public function withSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function withConditions(ConfiguredConditions $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function withActions(ConfiguredActions $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    public function withCreatedOn(SerializableDateTime $createdOn): self
    {
        $this->createdOn = $createdOn;

        return $this;
    }
}
