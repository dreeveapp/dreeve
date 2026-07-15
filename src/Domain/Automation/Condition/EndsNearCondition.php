<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;

final readonly class EndsNearCondition implements Condition
{
    use MatchesCoordinateWithinRadius;

    public function getLabel(): string
    {
        return 'Ends near';
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--ends-near';
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        return $this->coordinateMatches($this->endingCoordinate($activity), $configuration);
    }

    private function endingCoordinate(Activity $activity): ?Coordinate
    {
        $polyline = $activity->getEncodedPolyline();
        if (!$polyline instanceof EncodedPolyline) {
            return null;
        }

        $points = $polyline->decode();
        $pointCount = count($points);
        if ($pointCount < 2) {
            return null;
        }

        return Coordinate::createFromLatAndLng(
            Latitude::fromString((string) $points[$pointCount - 2]),
            Longitude::fromString((string) $points[$pointCount - 1]),
        );
    }
}
