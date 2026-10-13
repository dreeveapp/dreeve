<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.maintenance_progress_calculation')]
interface MaintenanceTaskProgressCalculation
{
    public function supports(IntervalUnit $intervalUnit): bool;

    public function calculate(ProgressCalculationContext $context): ?MaintenanceTaskProgress;
}
