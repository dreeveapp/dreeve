<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Automation\RuleConfiguration;

final readonly class ConditionEvaluationResult
{
    public function __construct(
        private ConditionType $type,
        private RuleConfiguration $configuration,
        private bool $matched,
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

    public function isMatched(): bool
    {
        return $this->matched;
    }
}
