<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

interface GearMaintenanceRepository
{
    public function find(): GearMaintenanceConfig;

    public function updateConfig(bool $isFeatureEnabled, bool $ignoreRetiredGear): void;
}
