<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\GeoMath;

trait MatchesCoordinateWithinRadius
{
    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => MatchOperator::WITHIN->value,
            'latitude' => 0.0,
            'longitude' => 0.0,
            'radius' => 1.0,
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForProximity()) {
            throw new InvalidAutomationRule(sprintf('Invalid proximity operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $latitude = $configuration->get('latitude');
        if ((!is_int($latitude) && !is_float($latitude)) || abs($latitude) > 90) {
            throw new InvalidAutomationRule('A "latitude" between -90 and 90 is required.');
        }

        $longitude = $configuration->get('longitude');
        if ((!is_int($longitude) && !is_float($longitude)) || abs($longitude) > 180) {
            throw new InvalidAutomationRule('A "longitude" between -180 and 180 is required.');
        }

        $radius = $configuration->get('radius');
        if ((!is_int($radius) && !is_float($radius)) || $radius <= 0) {
            throw new InvalidAutomationRule('A "radius" greater than 0 kilometer is required.');
        }
    }

    private function coordinateMatches(?Coordinate $coordinate, RuleConfiguration $configuration): bool
    {
        if (!$coordinate instanceof Coordinate) {
            return false;
        }

        $operator = $configuration->get('operator');
        $latitude = $configuration->get('latitude');
        $longitude = $configuration->get('longitude');
        $radius = $configuration->get('radius');
        assert(is_string($operator)
            && (is_int($latitude) || is_float($latitude))
            && (is_int($longitude) || is_float($longitude))
            && (is_int($radius) || is_float($radius)));

        $distanceInMeters = GeoMath::haversineDistance(
            lat1: (float) $latitude,
            lon1: (float) $longitude,
            lat2: $coordinate->getLatitude()->toFloat(),
            lon2: $coordinate->getLongitude()->toFloat(),
        );

        return MatchOperator::from($operator)->isSatisfiedBy($distanceInMeters <= (float) $radius * 1000.0);
    }
}
