<?php

declare(strict_types=1);

namespace App\Domain\Activity\Image;

use App\Application\Countries;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Context\TrustedVisitorCacheContext;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class PhotosFragment implements Fragment
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ActivityRepository $activityRepository,
        private SportTypeRepository $sportTypeRepository,
        private Countries $countries,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'photos';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        );
    }

    public function render(): string
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
