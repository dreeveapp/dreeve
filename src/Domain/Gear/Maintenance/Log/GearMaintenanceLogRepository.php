<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log;

use App\Domain\Gear\GearId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

interface GearMaintenanceLogRepository
{
    public function add(GearMaintenanceLog $gearMaintenanceLog): void;

    public function update(GearMaintenanceLog $gearMaintenanceLog): void;

    public function find(GearMaintenanceLogId $gearMaintenanceLogId): GearMaintenanceLog;

    public function delete(GearMaintenanceLogId $gearMaintenanceLogId): void;

    public function findAll(): GearMaintenanceLogs;

    public function existsForGear(GearId $gearId): bool;

    /**
     * @return array<string, SerializableDateTime>
     */
    public function findMostRecentPerMaintenanceTask(): array;
}
