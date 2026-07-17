<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PassesNearCondition implements Condition
{
    use MatchesCoordinateWithinRadius;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Passes near', domain: 'admin', locale: $locale);
    }

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $translator->trans(
            id: 'Route passes {operator} {radius} km of {latitude}, {longitude}',
            parameters: $this->proximityDescriptionParameters($configuration, $translator),
            domain: 'admin',
        );
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--passes-near';
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $polyline = $activity->getEncodedPolyline();
        if (!$polyline instanceof EncodedPolyline) {
            // A null polyline can never be located: no match for either operator.
            return false;
        }

        $operator = $configuration->get('operator');
        assert(is_string($operator));

        if (MatchOperator::WITHIN === MatchOperator::from($operator)) {
            // Passes near == any point sits within the radius; short-circuit on the first hit.
            foreach ($this->coordinates($polyline) as $coordinate) {
                if ($this->coordinateMatches($coordinate, $configuration)) {
                    return true;
                }
            }

            return false;
        }

        // "outside" == the route never comes near: every point must sit outside the radius.
        foreach ($this->coordinates($polyline) as $coordinate) {
            if (!$this->coordinateMatches($coordinate, $configuration)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return iterable<Coordinate>
     */
    private function coordinates(EncodedPolyline $polyline): iterable
    {
        $points = $polyline->decode();
        $counter = count($points);
        for ($i = 0; $i + 1 < $counter; $i += 2) {
            yield Coordinate::createFromLatAndLng(
                Latitude::fromString((string) $points[$i]),
                Longitude::fromString((string) $points[$i + 1]),
            );
        }
    }
}
