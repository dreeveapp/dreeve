<?php

declare(strict_types=1);

namespace App\Application\Navigation;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Twig\Environment;

final readonly class SideBar
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private GearRepository $gearRepository,
        private ChallengeRepository $challengeRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ImageRepository $imageRepository,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    public function render(?NavigationSection $activeSection): ?string
    {
        return $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: 'app-shell.sidebar.'.($activeSection->value ?? 'none'),
                cacheTags: CacheTags::of(
                    RootCacheTag::ACTIVITIES,
                    RootCacheTag::ACTIVITY_IMAGES,
                    RootCacheTag::CHALLENGES,
                    RootCacheTag::GEAR,
                ),
            ),
            render: fn (): string => $this->twig->load('html/navigation/sidebar.html.twig')->render([
                'activeSection' => $activeSection?->value,
                'totalActivityCount' => $this->activityIdRepository->count(),
                'completedChallenges' => $this->challengeRepository->count(),
                'totalPhotoCount' => $this->imageRepository->count(),
                'hasGear' => $this->gearRepository->hasGear(),
                'hasBestEfforts' => $this->activityBestEffortRepository->hasData(),
            ]),
        ))->getContent();
    }
}
