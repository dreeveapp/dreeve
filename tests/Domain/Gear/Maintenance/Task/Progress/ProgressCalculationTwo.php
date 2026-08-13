<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\ProgressCalculationContext;

class ProgressCalculationTwo implements MaintenanceTaskProgressCalculation
{
    public function supports(IntervalUnit $intervalUnit): bool
    {
        return true;
    }

    public function calculate(ProgressCalculationContext $context): MaintenanceTaskProgress
    {
        return MaintenanceTaskProgress::from(100, 100, 'test', 'test');
    }
}
