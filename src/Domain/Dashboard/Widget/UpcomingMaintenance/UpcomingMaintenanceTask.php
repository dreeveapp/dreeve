<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\UpcomingMaintenance;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Infrastructure\ValueObject\String\Name;

final readonly class UpcomingMaintenanceTask
{
    private function __construct(
        private Name $componentLabel,
        private Name $taskLabel,
        private int $intervalValue,
        private IntervalUnit $intervalUnit,
        private MaintenanceTaskProgress $progress,
    ) {
    }

    public static function from(
        Name $componentLabel,
        Name $taskLabel,
        int $intervalValue,
        IntervalUnit $intervalUnit,
        MaintenanceTaskProgress $progress,
    ): self {
        return new self(
            componentLabel: $componentLabel,
            taskLabel: $taskLabel,
            intervalValue: $intervalValue,
            intervalUnit: $intervalUnit,
            progress: $progress,
        );
    }

    public function getComponentLabel(): Name
    {
        return $this->componentLabel;
    }

    public function getTaskLabel(): Name
    {
        return $this->taskLabel;
    }

    public function getIntervalValue(): int
    {
        return $this->intervalValue;
    }

    public function getIntervalUnit(): IntervalUnit
    {
        return $this->intervalUnit;
    }

    public function getProgress(): MaintenanceTaskProgress
    {
        return $this->progress;
    }
}
