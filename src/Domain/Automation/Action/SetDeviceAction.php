<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SetDeviceAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Set recording device', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $configuration->getString('deviceName');
    }

    public function getPriority(): int
    {
        return 15;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--set-device';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'deviceName' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $deviceName = $configuration->get('deviceName');
        if (!is_string($deviceName) || '' === trim($deviceName)) {
            throw new InvalidAutomationRule('A "deviceName" is required.');
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $deviceName = $configuration->getString('deviceName');

        return $activity->withDeviceName($deviceName);
    }
}
