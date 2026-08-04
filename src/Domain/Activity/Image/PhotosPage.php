<?php

declare(strict_types=1);

namespace App\Domain\Activity\Image;

use App\Application\Countries;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheContexts;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\Context\TrustedVisitorCacheContext;
use App\Infrastructure\Http\Page\Page;
use App\Infrastructure\Http\Page\ProvidesCacheKeyFromPath;
use Twig\Environment;

final readonly class PhotosPage implements Page
{
    use ProvidesCacheKeyFromPath;

    public function __construct(
        private ImageRepository $imageRepository,
        private SportTypeRepository $sportTypeRepository,
        private Countries $countries,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'photos';
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheTags: CacheTags::of(CacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        );
    }

    public function render(): string
    {
        $images = $this->imageRepository->findAll();

        return $this->twig->load('html/photos.html.twig')->render([
            'images' => $images,
            'sportTypes' => $this->sportTypeRepository->findForImages(),
            'countries' => $this->countries->getUsedInPhotos(),
            'totalPhotoCount' => count($images),
        ]);
    }
}
