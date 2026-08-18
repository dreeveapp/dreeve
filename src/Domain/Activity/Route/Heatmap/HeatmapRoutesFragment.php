<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Heatmap;

use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\Route\Route;
use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Twig\UrlTwigExtension;

final readonly class HeatmapRoutesFragment implements Fragment
{
    public function __construct(
        private RouteRepository $routeRepository,
        private SettingsRepository $settingsRepository,
        private UrlTwigExtension $urlTwigExtension,
    ) {
    }

    public function getPath(): string
    {
        return 'heatmap/routes';
    }

    public function getType(): FragmentType
    {
        return FragmentType::DATA;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'heatmap.routes',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE),
        );
    }

    public function render(): string
    {
        return Json::encode($this->enrichedRoutes());
    }

    /**
     * @return Route[]
     */
    private function enrichedRoutes(): array
    {
        $appearance = $this->settingsRepository->appearance();

        $enrichedRoutes = [];
        foreach ($this->routeRepository->findAll() as $route) {
            $enrichedRoutes[] = $route
                ->withUnitSystemAndDateTimeFormat(
                    unitSystem: $appearance->getUnitSystem(),
                    dateAndTimeFormat: $appearance->getDateAndTimeFormat(),
                )
                ->withRelativeActivityUri($this->urlTwigExtension->toRelativeUrl('api/fragment/page/'.ActivityFragmentPath::for($route->getActivityId())));
        }

        return $enrichedRoutes;
    }
}
