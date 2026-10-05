<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EndsNearStartCondition implements Condition
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private ActivityRouteCoordinates $routeCoordinates,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Ends near start', domain: 'admin', locale: $locale);
    }

    public function getPriority(): int
    {
        return 75;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--ends-near-start';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => MatchOperator::WITHIN->value,
            'radius' => 500.0,
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForProximity()) {
            throw new InvalidAutomationRule(sprintf('Invalid proximity operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $radius = $configuration->get('radius');
        if ((!is_int($radius) && !is_float($radius)) || $radius <= 0) {
            throw new InvalidAutomationRule('A "radius" greater than 0 is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $start = $this->routeCoordinates->first($activity);
        $end = $this->routeCoordinates->last($activity);
        if (null === $start || null === $end) {
            return false;
        }

        $distanceInMeters = GeoMath::haversineDistance(
            lat1: $start->getLatitude()->toFloat(),
            lon1: $start->getLongitude()->toFloat(),
            lat2: $end->getLatitude()->toFloat(),
            lon2: $end->getLongitude()->toFloat(),
        );

        $radiusInMeters = $this->settingsRepository->appearance()->getUnitSystem()->proximity((float) $configuration->getNumber('radius'))->toMeter()->toFloat();

        return MatchOperator::from($configuration->getString('operator'))->isSatisfiedBy($distanceInMeters <= $radiusInMeters);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s %s',
            MatchOperator::from($configuration->getString('operator'))->trans($translator),
            (float) $configuration->getNumber('radius'),
            $this->settingsRepository->appearance()->getUnitSystem()->proximitySymbol(),
        );
    }
}
