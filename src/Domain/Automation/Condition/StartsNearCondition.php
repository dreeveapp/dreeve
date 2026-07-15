<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;

final readonly class StartsNearCondition implements Condition
{
    use MatchesCoordinateWithinRadius;

    public function getLabel(): string
    {
        return 'Starts near';
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--starts-near';
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        return $this->coordinateMatches($activity->getStartingCoordinate(), $configuration);
    }
}
