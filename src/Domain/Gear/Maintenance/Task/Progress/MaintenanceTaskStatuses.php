<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<MaintenanceTaskStatus>
 */
final class MaintenanceTaskStatuses extends Collection
{
    public function getItemClassName(): string
    {
        return MaintenanceTaskStatus::class;
    }

    public function getForTask(MaintenanceTaskId $maintenanceTaskId): ?MaintenanceTaskStatus
    {
        $statuses = $this->filter(
            fn (MaintenanceTaskStatus $status): bool => $maintenanceTaskId == $status->getMaintenanceTaskId()
        )->toArray();

        return reset($statuses) ?: null;
    }
}
