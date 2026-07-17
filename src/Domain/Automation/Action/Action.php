<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.automation_rule.action')]
interface Action extends TranslatableInterface
{
    public function getPriority(): int;

    public function getTemplateName(): string;

    public function getDefaultConfiguration(): RuleConfiguration;

    public function guardValidConfiguration(RuleConfiguration $configuration): void;

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity;

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): ?string;
}
