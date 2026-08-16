<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Gear\Sensor\SensorRepository;
use App\Domain\Gear\Sensor\SensorType;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ConnectedSensorsCondition implements Condition, ConditionallyAvailable
{
    public function __construct(
        private SensorRepository $sensorRepository,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Connected sensors', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s',
            MatchOperator::from($configuration->getString('operator'))->trans($translator),
            implode(', ', array_map(
                static fn (mixed $sensorType): string => SensorType::from((string) $sensorType)->trans($translator),
                $configuration->getArray('sensorTypes'),
            )),
        );
    }

    public function isAvailable(): bool
    {
        return !$this->sensorRepository->findAll()->isEmpty();
    }

    public function getPriority(): int
    {
        return 15;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--connected-sensors';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => MatchOperator::IS_ONE_OF->value,
            'sensorTypes' => [],
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForSet()) {
            throw new InvalidAutomationRule(sprintf('Invalid connected sensors operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $sensorTypes = $configuration->get('sensorTypes');
        if (!is_array($sensorTypes) || [] === $sensorTypes) {
            throw new InvalidAutomationRule('At least one sensor is required.');
        }

        foreach ($sensorTypes as $sensorType) {
            if (!is_string($sensorType) || null === SensorType::tryFrom($sensorType)) {
                throw new InvalidAutomationRule(sprintf('Invalid sensor "%s".', is_scalar($sensorType) ? (string) $sensorType : ''));
            }
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $connectedSensors = $activity->getConnectedSensors();
        if (!$connectedSensors instanceof ConnectedSensors) {
            return false;
        }

        return MatchOperator::from($configuration->getString('operator'))->isSatisfiedBy(
            $connectedSensors->hasAnyOf(...array_map(
                static fn (mixed $sensorType): SensorType => SensorType::from((string) $sensorType),
                $configuration->getArray('sensorTypes'),
            ))
        );
    }
}
