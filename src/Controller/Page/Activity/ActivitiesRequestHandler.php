<?php

declare(strict_types=1);

namespace App\Controller\Page\Activity;

use App\Application\Countries;
use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivitiesRequestHandler
{
    public function __construct(
        private QueryBus $queryBus,
        private SportTypeRepository $sportTypeRepository,
        private RecordingDeviceRepository $recordingDeviceRepository,
        private GearRepository $gearRepository,
        private Countries $countries,
        private Environment $twig,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/activities', name: 'activities', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'activities',
                cacheTags: CacheTags::of(
                    RootCacheTag::ACTIVITIES,
                    RootCacheTag::GEAR,
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::ACTIVITIES,
        );
    }

    private function renderFor(): string
    {
        return $this->twig->load('html/activity/activities.html.twig')->render([
            'sportTypes' => $this->sportTypeRepository->findAll(),
            'devices' => $this->recordingDeviceRepository->findAll(),
            'activityTotals' => $this->queryBus->ask(new FindActivityTotals()),
            'countries' => $this->countries->getUsedInActivities(),
            'gears' => $this->gearRepository->findAllUsed(),
        ]);
    }
}
