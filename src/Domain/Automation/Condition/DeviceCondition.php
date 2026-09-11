<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DeviceCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Recording device', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s',
            MatchOperator::from($configuration->getString('operator'))->trans($translator),
            $configuration->getString('deviceName'),
        );
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--device';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => MatchOperator::IS->value,
            'deviceName' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForSingleValue()) {
            throw new InvalidAutomationRule(sprintf('Invalid device operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $deviceName = $configuration->get('deviceName');
        if (!is_string($deviceName) || '' === trim($deviceName)) {
            throw new InvalidAutomationRule('A "deviceName" is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $deviceName = $configuration->getString('deviceName');

        $activityDeviceName = $activity->getDeviceName();
        $activityMatchesDevice = null !== $activityDeviceName
            && (string) RecordingDeviceId::fromName($activityDeviceName) === (string) RecordingDeviceId::fromName($deviceName);

        return MatchOperator::from($operator)->isSatisfiedBy($activityMatchesDevice);
    }
}
