<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition\ConfiguredCondition;

use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\RuleConfiguration;

final readonly class ConfiguredCondition implements \JsonSerializable
{
    public function __construct(
        private ConditionType $type,
        private RuleConfiguration $configuration,
    ) {
    }

    public function getType(): ConditionType
    {
        return $this->type;
    }

    public function getConfiguration(): RuleConfiguration
    {
        return $this->configuration;
    }

    /**
     * @return array{type: string, config: RuleConfiguration}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'config' => $this->configuration,
        ];
    }
}
