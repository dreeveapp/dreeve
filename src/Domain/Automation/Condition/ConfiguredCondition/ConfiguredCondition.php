<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition\ConfiguredCondition;

use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\RuleConfiguration;

interface ConfiguredCondition extends \JsonSerializable
{
    public function getType(): ConditionType;

    public function getConfiguration(): RuleConfiguration;
}
