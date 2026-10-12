<?php

declare(strict_types=1);

namespace App\Controller\Page\Gear;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Gear\Gear;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearComponents;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class GearMaintenanceRequestHandler
{
    public function __construct(
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearComponentRepository $gearComponentRepository,
        private GearRepository $gearRepository,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private Clock $clock,
        private Environment $twig,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/gear/maintenance', name: 'gear_maintenance', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'gear.maintenance',
                cacheTags: CacheTags::of(
                    RootCacheTag::GEAR_MAINTENANCE,
                    RootCacheTag::ACTIVITIES,
                    RootCacheTag::GEAR,
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
                ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::GEAR,
        );
    }

    private function renderFor(): string
    {
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        $gears = $this->gearRepository->findAll();

        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return $this->twig->load('html/gear/maintenance/gear-maintenance-disabled.html.twig')->render([
                'isFeatureEnabled' => false,
            ]);
        }

        $now = $this->clock->getCurrentDateTimeImmutable();
        $gearComponents = $this->gearComponentRepository->findAll();
        $gearsWithComponents = [];
        $componentsNotFitted = GearComponents::empty();
        $parentComponentLabels = [];
        /** @var GearComponent $gearComponent */
        foreach ($gearComponents->withoutRetired() as $gearComponent) {
            $parentComponents = $gearComponents->getParentComponentsOf($gearComponent, $now);
            if (!$parentComponents->isEmpty()) {
                $parentComponentLabels[(string) $gearComponent->getId()] = implode(', ', $parentComponents->map(
                    static fn (GearComponent $parentComponent): string => (string) $parentComponent->getLabel(),
                ));
            }

            $currentGearIds = $gearComponents->getGearIdsCurrentlyUsing($gearComponent, $now);
            if ($currentGearIds->isEmpty()) {
                $componentsNotFitted->add($gearComponent);
            }

            foreach ($currentGearIds as $gearId) {
                if (!($gear = $gears->getByGearId($gearId)) instanceof Gear) {
                    continue;
                }
                if ($gear->isRetired() && $gearMaintenanceConfig->ignoreRetiredGear()) {
                    continue;
                }

                $gearsWithComponents[(string) $gear->getId()] ??= [
                    'gear' => $gear,
                    'components' => GearComponents::empty(),
                ];
                $gearsWithComponents[(string) $gear->getId()]['components']->add($gearComponent);
            }
        }

        return $this->twig->load('html/gear/maintenance/gear-maintenance.html.twig')->render([
            'gearsWithComponents' => array_values($gearsWithComponents),
            'componentsNotFitted' => $componentsNotFitted,
            'parentComponentLabels' => $parentComponentLabels,
            'gearIdsThatHaveDueTasks' => $this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks(),
            'maintenanceTaskStatuses' => $this->maintenanceTaskProgressCalculator->calculateStatuses(),
            'isFeatureEnabled' => true,
        ]);
    }
}
