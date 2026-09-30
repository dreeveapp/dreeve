<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Application\AppShell;
use App\Application\Countries;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Context\TrustedVisitorCacheContext;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class PhotosRequestHandler
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ActivityRepository $activityRepository,
        private SportTypeRepository $sportTypeRepository,
        private Countries $countries,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/photos', name: 'photos', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'photos',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
                cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
        );

        return new PrivateNoStoreHtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::PHOTOS,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(): string
    {
        $images = $this->imageRepository->findAll();

        return $this->twig->load('html/photos.html.twig')->render([
            'images' => $images,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($images->getActivityIds())->keyByActivityId(),
            'sportTypes' => $this->sportTypeRepository->findForImages(),
            'countries' => $this->countries->getUsedInPhotos(),
            'totalPhotoCount' => count($images),
        ]);
    }
}
