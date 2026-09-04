<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MarkAsGroupActivityAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Mark as group activity', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): ?string
    {
        return null;
    }

    public function getPriority(): int
    {
        return 25;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--mark-as-group-activity';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::empty();
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        return $activity->withGroupActivity(true);
    }
}
