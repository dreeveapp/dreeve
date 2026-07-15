<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'AutomationRule')]
final readonly class AutomationRule
{
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private AutomationRuleId $automationRuleId,
        #[ORM\Column(type: 'string')]
        private string $label,
        #[ORM\Column(type: 'boolean')]
        private bool $isEnabled,
        #[ORM\Column(type: 'integer')]
        private int $sortOrder,
        #[ORM\Column(type: 'json')]
        private ConfiguredConditions $conditions,
        #[ORM\Column(type: 'json')]
        private ConfiguredActions $actions,
        #[ORM\Column(type: 'datetime_immutable')]
        private SerializableDateTime $createdOn,
    ) {
    }

    public static function create(
        AutomationRuleId $automationRuleId,
        string $label,
        bool $isEnabled,
        int $sortOrder,
        ConfiguredConditions $conditions,
        ConfiguredActions $actions,
        SerializableDateTime $createdOn,
    ): self {
        return new self(
            automationRuleId: $automationRuleId,
            label: $label,
            isEnabled: $isEnabled,
            sortOrder: $sortOrder,
            conditions: $conditions,
            actions: $actions,
            createdOn: $createdOn,
        );
    }

    public static function fromState(
        AutomationRuleId $automationRuleId,
        string $label,
        bool $isEnabled,
        int $sortOrder,
        ConfiguredConditions $conditions,
        ConfiguredActions $actions,
        SerializableDateTime $createdOn,
    ): self {
        return new self(
            automationRuleId: $automationRuleId,
            label: $label,
            isEnabled: $isEnabled,
            sortOrder: $sortOrder,
            conditions: $conditions,
            actions: $actions,
            createdOn: $createdOn,
        );
    }

    public function getId(): AutomationRuleId
    {
        return $this->automationRuleId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function withLabel(string $label): self
    {
        return clone ($this, [
            'label' => $label,
        ]);
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function withIsEnabled(bool $isEnabled): self
    {
        return clone ($this, [
            'isEnabled' => $isEnabled,
        ]);
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function withSortOrder(int $sortOrder): self
    {
        return clone ($this, [
            'sortOrder' => $sortOrder,
        ]);
    }

    public function getConditions(): ConfiguredConditions
    {
        return $this->conditions;
    }

    public function withConditions(ConfiguredConditions $conditions): self
    {
        return clone ($this, [
            'conditions' => $conditions,
        ]);
    }

    public function getActions(): ConfiguredActions
    {
        return $this->actions;
    }

    public function withActions(ConfiguredActions $actions): self
    {
        return clone ($this, [
            'actions' => $actions,
        ]);
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return $this->createdOn;
    }
}
