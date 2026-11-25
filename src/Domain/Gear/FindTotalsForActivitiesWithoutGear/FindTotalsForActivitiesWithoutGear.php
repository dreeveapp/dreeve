<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindTotalsForActivitiesWithoutGear;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Gear\FindTotalsForActivitiesWithoutGear\FindTotalsForActivitiesWithoutGearResponse>
 */
final readonly class FindTotalsForActivitiesWithoutGear implements Query
{
}
