<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.automation_rule.condition')]
interface Condition
{
    public function getLabel(): string;

    public function getTemplateName(): string;

    public function getDefaultConfiguration(): RuleConfiguration;

    public function guardValidConfiguration(RuleConfiguration $configuration): void;

    public function matches(Activity $activity, RuleConfiguration $configuration): bool;
}
