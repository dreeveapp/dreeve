<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\ImportActivities;

use App\Domain\Activity\ActivityVisibility;
use App\Infrastructure\ValueObject\BackedEnumCollection;

/**
 * @extends BackedEnumCollection<ActivityVisibility>
 */
final class ActivityVisibilitiesToImport extends BackedEnumCollection
{
    public function getItemClassName(): string
    {
        return ActivityVisibility::class;
    }

    /**
     * @param string[] $visibilities
     */
    public static function from(array $visibilities): self
    {
        if (0 === count($visibilities)) {
            // Import all visibilities.
            return self::fromArray(ActivityVisibility::cases());
        }

        return self::fromValues($visibilities);
    }
}
