<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Domain\Activity\Activity;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<Activity>
 */
final class ImportedActivities extends Collection
{
    public function getItemClassName(): string
    {
        return Activity::class;
    }
}
