<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Time\Clock\Clock;
use Twig\Environment;

final readonly class GearMaintenanceDueFragment implements Fragment
{
    public function __construct(
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'gear/maintenance-due';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PARTIAL;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'gear.maintenance-due',
            cacheTags: CacheTags::of(
                RootCacheTag::GEAR_MAINTENANCE,
                RootCacheTag::ACTIVITIES,
                RootCacheTag::GEAR,
            ),
            ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/gear/maintenance/_maintenance-due.html.twig')->render([
            'maintenanceTaskIsDue' => !$this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks()->isEmpty(),
        ]);
    }
}
