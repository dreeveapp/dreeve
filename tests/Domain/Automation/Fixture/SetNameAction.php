<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Fixture;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityName;
use App\Domain\Automation\Action\Action;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SetNameAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Set name', domain: 'admin', locale: $locale);
    }

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $translator->trans('Set name', domain: 'admin', locale: $locale);
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getTemplateName(): string
    {
        return 'action/set-name.html.twig';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['name' => '']);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        if ('' === trim((string) $configuration->get('name'))) {
            throw new InvalidAutomationRule('A "name" is required.');
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        return $activity->withName(ActivityName::fromString((string) $configuration->get('name')));
    }
}
