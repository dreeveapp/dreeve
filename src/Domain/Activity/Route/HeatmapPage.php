<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Http\Page\Page;
use Twig\Environment;

final readonly class HeatmapPage implements Page
{
    public function __construct(
        private RouteRepository $routeRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'heatmap';
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(CacheTag::ACTIVITY_ROUTE, CacheTag::SETTINGS_MAPS),
        );
    }

    public function render(): string
    {
        $routes = $this->routeRepository->findAll();
        $sportTypesOnTheMap = $routes->map(fn (Route $route): SportType => $route->getSportType());

        return $this->twig->load('html/heatmap.html.twig')->render([
            'numberOfRoutes' => count($routes),
            'sportTypes' => array_filter(
                SportType::cases(),
                fn (SportType $sportType): bool => in_array($sportType, $sportTypesOnTheMap, true)
            ),
            'numberOfCountriesWithWorkouts' => count(array_unique(array_merge(...$routes->map(
                fn (Route $route): array => $route->getRouteGeography()->getPassedThroughCountries()
            )))),
            'heatmapConfig' => $this->settingsRepository->maps()->getHeatmapConfig(),
        ]);
    }
}
