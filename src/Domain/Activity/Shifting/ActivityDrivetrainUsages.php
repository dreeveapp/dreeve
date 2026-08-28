<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityDrivetrainUsage>
 */
final class ActivityDrivetrainUsages extends Collection
{
    public function getItemClassName(): string
    {
        return ActivityDrivetrainUsage::class;
    }

    public function filterOnPosition(DrivetrainPosition $position): self
    {
        return $this->filter(fn (ActivityDrivetrainUsage $drivetrainUsage): bool => $position === $drivetrainUsage->getPosition());
    }
}
