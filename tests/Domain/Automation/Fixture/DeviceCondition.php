<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Fixture;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Condition\Condition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DeviceCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Recorded with device', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): ?string
    {
        return (string) $configuration->get('deviceName');
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getTemplateName(): string
    {
        return 'condition/device.html.twig';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['deviceName' => '']);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        if ('' === trim((string) $configuration->get('deviceName'))) {
            throw new InvalidAutomationRule('A "deviceName" is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        return $activity->getDeviceName() === $configuration->get('deviceName');
    }
}
