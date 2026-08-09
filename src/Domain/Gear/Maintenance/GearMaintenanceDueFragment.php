<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class GearMaintenanceDueFragment implements Fragment
{
    public function __construct(
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'gear/maintenance-due';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::none();
    }

    public function render(): string
    {
        return $this->twig->load('html/gear/maintenance/_maintenance-due.html.twig')->render([
            'maintenanceTaskIsDue' => !$this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks()->isEmpty(),
        ]);
    }
}
