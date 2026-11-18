<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Heatmap;

use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Geography\Country\CountryBoundaries;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\ConditionalJsonResponse;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class HeatmapCountriesRequestHandler
{
    public function __construct(
        private RouteRepository $routeRepository,
        private CountryBoundaries $countryBoundaries,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/heatmap/countries', name: 'heatmap_countries', methods: ['GET'])]
    public function handle(Request $request): ConditionalJsonResponse
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'heatmap.countries',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE),
            ),
            render: fn (): string => $this->renderFor(),
        );

        return new ConditionalJsonResponse($render, $request, '[]');
    }

    private function renderFor(): string
    {
        return Json::encode($this->countryBoundaries->geoJsonFor(
            $this->routeRepository->findSummary()->getCountries()
        ));
    }
}
