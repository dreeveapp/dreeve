<?php

declare(strict_types=1);

namespace App\Controller\Page\Activity\Collection;

use App\Application\Navigation\NavigationSection;
use App\Application\PageMetadata\PageMetadata;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionCacheTag;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionWithActivities;
use App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection\FindNumberOfActivitiesPerActivityCollection;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Activity\RealWorldMap;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityCollectionRequestHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private Environment $twig,
        private PageRenderer $pageRenderer,
        private ImageRepository $imageRepository,
    ) {
    }

    #[Route(path: '/collections/{activityCollectionId}', name: 'activity_collection', requirements: ['activityCollectionId' => 'activityCollection-[^/]+'], methods: ['GET'])]
    public function handle(string $activityCollectionId): HtmlResponse
    {
        $activityCollection = $this->activityCollectionRepository->find(ActivityCollectionId::fromString($activityCollectionId));
        if (0 === $this->queryBus->ask(new FindNumberOfActivitiesPerActivityCollection())->getNumberOfActivitiesFor($activityCollection->getId())) {
            throw new NotFoundHttpException('Not found');
        }

        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activity-collections.%s', $activityCollection->getId()->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCollectionCacheTag::for($activityCollection->getId()),
                    RootCacheTag::ACTIVITY_ROUTE,
                    ...$activityCollection->getActivityIds()->map(static fn (ActivityId $activityId): CacheTag => ActivityCacheTag::for($activityId)),
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor($activityCollection),
            navigationSection: NavigationSection::ACTIVITY_COLLECTIONS,
            metadata: new PageMetadata(
                title: $activityCollection->getName(),
                description: $activityCollection->getDescription(),
            ),
        );
    }

    private function renderFor(ActivityCollection $activityCollection): string
    {
        $activityCollectionWithActivities = ActivityCollectionWithActivities::create(
            activityCollection: $activityCollection,
            activities: $this->activityRepository->findByIds($activityCollection->getActivityIds()),
        );

        return $this->twig->load(sprintf(
            'html/activity/collection/%s.html.twig',
            $activityCollection->getType()->getTemplateName(),
        ))->render([
            'activityCollectionWithActivities' => $activityCollectionWithActivities,
            'leafletMap' => $activityCollectionWithActivities->getActivitiesWithARealWorldRoute()->isEmpty() ? null : new RealWorldMap(),
            'images' => $this->imageRepository->findByActivityIds($activityCollection->getActivityIds())->reverse(),
        ]);
    }
}
