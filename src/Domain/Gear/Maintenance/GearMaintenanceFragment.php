<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Gears;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Time\Clock\Clock;
use Twig\Environment;

final readonly class GearMaintenanceFragment implements Fragment
{
    public function __construct(
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearRepository $gearRepository,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'gear/maintenance';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'gear.maintenance',
            cacheTags: CacheTags::of(
                RootCacheTag::GEAR_MAINTENANCE,
                RootCacheTag::ACTIVITIES,
                RootCacheTag::GEAR,
            ),
            cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
        );
    }

    public function render(): string
    {
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        $gears = $this->gearRepository->findAll();

        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return $this->twig->load('html/gear/maintenance/gear-maintenance-disabled.html.twig')->render([
                'isFeatureEnabled' => false,
            ]);
        }

        $gearsThatAreAttachedToComponents = Gears::empty();
        $gearIdsThatAreAttachedToComponents = [];
        /** @var GearComponent $gearComponent */
        foreach ($gearMaintenanceConfig->getGearComponents() as $gearComponent) {
            foreach ($gearComponent->getAttachedTo() as $attachedToGearId) {
                if (!($gear = $gears->getByGearId($attachedToGearId)) instanceof Gear) {
                    continue;
                }
                if (in_array((string) $gear->getId(), $gearIdsThatAreAttachedToComponents)) {
                    continue;
                }
                if ($gear->isRetired() && $gearMaintenanceConfig->ignoreRetiredGear()) {
                    continue;
                }

                $gearsThatAreAttachedToComponents->add($gear);
                $gearIdsThatAreAttachedToComponents[] = (string) $gear->getId();
            }
        }

        return $this->twig->load('html/gear/maintenance/gear-maintenance.html.twig')->render([
            'gearsAttachedToComponents' => $gearsThatAreAttachedToComponents,
            'gearComponents' => $gearMaintenanceConfig->getGearComponents(),
            'gearIdsThatHaveDueTasks' => $this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks(),
            'maintenanceTaskStatuses' => $this->maintenanceTaskProgressCalculator->calculateStatuses(),
            'isFeatureEnabled' => true,
        ]);
    }
}
