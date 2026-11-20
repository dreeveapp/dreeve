<?php

declare(strict_types=1);

namespace App\Controller\Page\Activity\Collection;

use App\Application\Navigation\NavigationSection;
use App\Application\PageMetadata\PageMetadata;
use App\Application\PageRenderer;
use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionCacheTag;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionWithActivities;
use App\Domain\Activity\Collection\FindNumberOfActivitiesPerActivityCollection\FindNumberOfActivitiesPerActivityCollection;
use App\Domain\Activity\Media\ActivityMediaRepository;
use App\Domain\Activity\RealWorldMap;
use App\Domain\Media\MediaItemCacheTag;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Response\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private ActivityMediaRepository $activityMediaRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(path: '/collections/{activityCollectionId}', name: 'activity_collection', requirements: ['activityCollectionId' => 'activityCollection-[^/]+'], methods: ['GET'])]
    public function handle(string $activityCollectionId): HtmlResponse
    {
        $activityCollection = $this->activityCollectionRepository->find(ActivityCollectionId::fromString($activityCollectionId));
        if (0 === $this->queryBus->ask(new FindNumberOfActivitiesPerActivityCollection())->getNumberOfActivitiesFor($activityCollection->getId())) {
            throw new NotFoundHttpException('Not found');
        }

        $activities = $this->activityRepository->findByIds($activityCollection->getActivityIds());

        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activity-collections.%s', $activityCollection->getId()->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCollectionCacheTag::for($activityCollection->getId()),
                    RootCacheTag::ACTIVITY_ROUTE,
                    ...$activityCollection->getActivityIds()->map(static fn (ActivityId $activityId): CacheTag => ActivityCacheTag::for($activityId)),
                    ...array_merge(...$activities->map(static fn (Activity $activity): array => MediaItemCacheTag::forAll($activity->getMediaItemIds()))),
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(
                activityCollection: $activityCollection,
                activities: $activities,
            ),
            navigationSection: NavigationSection::ACTIVITY_COLLECTIONS,
            metadata: new PageMetadata(
                title: $activityCollection->getName(),
                description: $activityCollection->getDescription(),
                imagePath: $this->urlGenerator->generate('activity_collection_og_image', ['activityCollectionId' => (string) $activityCollection->getId()]),
            ),
        );
    }

    private function renderFor(ActivityCollection $activityCollection, Activities $activities): string
    {
        $activityCollectionWithActivities = ActivityCollectionWithActivities::create(
            activityCollection: $activityCollection,
            activities: $activities,
        );

        return $this->twig->load(sprintf(
            'html/activity/collection/%s.html.twig',
            $activityCollection->getType()->getTemplateName(),
        ))->render([
            'activityCollectionWithActivities' => $activityCollectionWithActivities,
            'leafletMap' => $activityCollectionWithActivities->getActivitiesWithARealWorldRoute()->isEmpty() ? null : new RealWorldMap(),
            'mediaItems' => $this->activityMediaRepository->findByActivityIds($activityCollection->getActivityIds())->reverse(),
        ]);
    }
}
