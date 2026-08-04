<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log;

use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

interface GearMaintenanceLogRepository
{
    public function add(GearMaintenanceLog $gearMaintenanceLog): void;

    public function update(GearMaintenanceLog $gearMaintenanceLog): void;

    public function find(GearMaintenanceLogId $gearMaintenanceLogId): GearMaintenanceLog;

    public function delete(GearMaintenanceLogId $gearMaintenanceLogId): void;

    public function findAll(): GearMaintenanceLogs;

    public function findMostRecentForMaintenanceTask(MaintenanceTaskId $maintenanceTaskId): ?GearMaintenanceLog;

    /**
     * @return array<string, SerializableDateTime> the date of the most recent maintenance, keyed by maintenance task id
     */
    public function findMostRecentPerMaintenanceTask(): array;
}
