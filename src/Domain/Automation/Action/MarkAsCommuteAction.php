<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MarkAsCommuteAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Mark as commute', domain: 'admin', locale: $locale);
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--mark-as-commute';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'isCommute' => true,
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        return $activity->withCommute((bool) $configuration->get('isCommute'));
    }
}
