<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\Application\Countries;
use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Context\TrustedVisitorCacheContext;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
        private PageRenderer $pageRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/photos', name: 'photos', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'photos',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
                cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::PHOTOS,
            openGraph: new OpenGraph(
                path: $this->urlGenerator->generate('photos'),
                title: $this->translator->trans('Photos'),
                description: $this->translator->trans('Photos from all activities'),
            ),
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
