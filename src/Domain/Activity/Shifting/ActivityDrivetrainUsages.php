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

    public function getFrontRings(): self
    {
        return $this->filterOnPosition(DrivetrainPosition::FRONT);
    }

    public function getRearCogs(): self
    {
        return $this->filterOnPosition(DrivetrainPosition::REAR);
    }

    public function getTotalShiftCount(): int
    {
        return (int) $this->sum(fn (ActivityDrivetrainUsage $drivetrainUsage): int => $drivetrainUsage->getShiftCount());
    }

    public function getTotalTimeInSeconds(): int
    {
        return (int) $this->sum(fn (ActivityDrivetrainUsage $drivetrainUsage): int => $drivetrainUsage->getTimeInSeconds());
    }

    public function getTimePercentageFor(ActivityDrivetrainUsage $drivetrainUsage): float
    {
        $totalTimeInSeconds = $this->getTotalTimeInSeconds();

        return $totalTimeInSeconds > 0 ? $drivetrainUsage->getTimeInSeconds() / $totalTimeInSeconds * 100 : 0.0;
    }
}
