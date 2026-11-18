<?php

declare(strict_types=1);

namespace App\Controller\Page\Activity\Collection;

use App\Application\Navigation\NavigationSection;
use App\Application\PageMetadata\PageMetadata;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionsWithActivities;
use App\Domain\Activity\Collection\ActivityCollectionWithActivities;
use App\Domain\Activity\RealWorldMap;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Twig\Environment;

#[AsController]
final readonly class ActivityCollectionsRequestHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ActivityRepository $activityRepository,
        private Environment $twig,
        private PageRenderer $pageRenderer,
        private MediaItemRepository $mediaItemRepository,
    ) {
    }

    #[Route(path: '/collections', name: 'activity_collections', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        if (!$this->activityCollectionRepository->hasActivityCollectionsWithActivities()) {
            throw new NotFoundHttpException('Not found');
        }

        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'activity-collections',
                cacheTags: CacheTags::of(
                    RootCacheTag::ACTIVITY_COLLECTIONS,
                    RootCacheTag::ACTIVITIES,
                    RootCacheTag::ACTIVITY_IMAGES,
                    RootCacheTag::ACTIVITY_ROUTE,
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::ACTIVITY_COLLECTIONS,
            metadata: new PageMetadata(
                title: new TranslatableMessage('Activity collections'),
            ),
        );
    }

    private function renderFor(): string
    {
        $activityCollections = $this->activityCollectionRepository->findAll();
        $activityCollectionsWithActivities = ActivityCollectionsWithActivities::create(
            activityCollections: $activityCollections,
            activities: $this->activityRepository->findByIds($activityCollections->getActivityIds()),
        );

        $coverMediaItems = [];
        foreach ($this->mediaItemRepository->findByIds($activityCollectionsWithActivities->getCoverMediaItemIds()) as $mediaItem) {
            $coverMediaItems[(string) $mediaItem->getId()] = $mediaItem;
        }

        return $this->twig->load('html/activity/collection/activity-collections.html.twig')->render([
            'activityCollectionsWithActivities' => ActivityCollectionsWithActivities::fromArray($activityCollectionsWithActivities->map(
                static fn (ActivityCollectionWithActivities $activityCollectionWithActivities): ActivityCollectionWithActivities => $activityCollectionWithActivities->withCoverMediaItem(
                    $coverMediaItems[(string) $activityCollectionWithActivities->getCoverMediaItemId()] ?? null,
                ),
            )),
            'leafletMap' => new RealWorldMap(),
        ]);
    }
}
