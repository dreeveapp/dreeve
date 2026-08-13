<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class MaintenanceTaskStatus
{
    private function __construct(
        private MaintenanceTaskId $maintenanceTaskId,
        private ?SerializableDateTime $lastPerformedOn,
        private ?MaintenanceTaskProgress $progress,
    ) {
    }

    public static function from(
        MaintenanceTaskId $maintenanceTaskId,
        ?SerializableDateTime $lastPerformedOn,
        ?MaintenanceTaskProgress $progress,
    ): self {
        return new self(
            maintenanceTaskId: $maintenanceTaskId,
            lastPerformedOn: $lastPerformedOn,
            progress: $progress,
        );
    }

    public function getMaintenanceTaskId(): MaintenanceTaskId
    {
        return $this->maintenanceTaskId;
    }

    public function getLastPerformedOn(): ?SerializableDateTime
    {
        return $this->lastPerformedOn;
    }

    public function getProgress(): ?MaintenanceTaskProgress
    {
        return $this->progress;
    }

    public function isDue(): bool
    {
        return $this->progress?->isDue() ?? false;
    }
}
