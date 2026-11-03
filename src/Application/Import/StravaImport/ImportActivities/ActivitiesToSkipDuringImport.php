<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\ImportActivities;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityId>
 */
final class ActivitiesToSkipDuringImport extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityId::class;
    }

    /**
     * @param string[] $activityIds
     */
    public static function from(array $activityIds): self
    {
        if (0 === count($activityIds)) {
            return self::empty();
        }

        return self::fromArray(array_map(
            ActivityId::fromUnprefixed(...),
            $activityIds
        ));
    }
}
