<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DeviceCondition implements Condition
{
    public function __construct(
        private RecordingDeviceRepository $recordingDeviceRepository,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Recording device', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        $deviceId = $configuration->getString('deviceId');

        try {
            $device = $this->recordingDeviceRepository->find(RecordingDeviceId::fromUnprefixed($deviceId))->getName();
        } catch (EntityNotFound) {
            $device = $deviceId;
        }

        return sprintf(
            '%s %s',
            MatchOperator::from($configuration->getString('operator'))->trans($translator),
            $device,
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
            'deviceId' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForSingleValue()) {
            throw new InvalidAutomationRule(sprintf('Invalid device operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $deviceId = $configuration->get('deviceId');
        if (!is_string($deviceId) || '' === trim($deviceId)) {
            throw new InvalidAutomationRule('A "deviceId" is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $deviceId = $configuration->getString('deviceId');

        $deviceName = $activity->getDeviceName();
        $activityMatchesDevice = null !== $deviceName
            && (string) RecordingDeviceId::fromName($deviceName) === (string) RecordingDeviceId::fromUnprefixed($deviceId);

        return MatchOperator::from($operator)->isSatisfiedBy($activityMatchesDevice);
    }
}
