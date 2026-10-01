<?php

declare(strict_types=1);

namespace App\Controller\Page\Gear;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Gear\Gear;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Gears;
use App\Domain\Gear\Maintenance\GearComponent;
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
