<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SetSportTypeAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Set sport type', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return SportType::from($configuration->getString('sportType'))->trans($translator);
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--set-sport-type';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'sportType' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $sportType = $configuration->get('sportType');
        if (!is_string($sportType) || null === SportType::tryFrom($sportType)) {
            throw new InvalidAutomationRule(sprintf('Invalid sport type "%s".', is_scalar($sportType) ? (string) $sportType : ''));
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $sportType = $configuration->getString('sportType');

        return $activity->withSportType(SportType::from($sportType));
    }
}
