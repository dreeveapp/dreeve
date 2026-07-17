<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.automation_rule.condition')]
interface Condition extends TranslatableInterface
{
    public function getPriority(): int;

    public function getTemplateName(): string;

    public function getDefaultConfiguration(): RuleConfiguration;

    public function guardValidConfiguration(RuleConfiguration $configuration): void;

    public function matches(Activity $activity, RuleConfiguration $configuration): bool;

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string;
}
