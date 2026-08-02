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
        // The appearance settings this render reads: photos.hidePhotosForSportTypes (grid contents,
        // photo count and filter options), sportTypesSortingOrder (filter order), dateFormat.short
        // (the per photo date) and locale (every translated string, sport type label, country name
        // and Carbon formatted date part).
        return Cacheability::for(
            cacheTags: CacheTags::of(CacheTag::ACTIVITIES, CacheTag::SETTINGS_APPEARANCE),
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
