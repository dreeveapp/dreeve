<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityName;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SetNameAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Set name', domain: 'admin', locale: $locale);
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--set-name';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'name' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $name = $configuration->get('name');
        if (!is_string($name) || '' === trim($name)) {
            throw new InvalidAutomationRule('A "name" is required.');
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $name = $configuration->get('name');
        assert(is_string($name));

        return $activity->withName(ActivityName::fromString($name));
    }
}
