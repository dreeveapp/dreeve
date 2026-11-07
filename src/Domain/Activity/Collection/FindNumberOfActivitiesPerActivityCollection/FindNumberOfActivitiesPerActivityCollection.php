<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection\FindNumberOfActivitiesPerActivityCollectionResponse>
 */
final readonly class FindNumberOfActivitiesPerActivityCollection implements Query
{
}
