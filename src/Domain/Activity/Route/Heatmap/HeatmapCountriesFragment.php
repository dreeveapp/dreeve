<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Heatmap;

use App\Domain\Activity\Route\RouteRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Serialization\Json;

final readonly class HeatmapCountriesFragment implements Fragment
{
    public function __construct(
        private RouteRepository $routeRepository,
        private CountryBoundaries $countryBoundaries,
    ) {
    }

    public function getPath(): string
    {
        return 'heatmap/countries';
    }

    public function getType(): FragmentType
    {
        return FragmentType::DATA;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'heatmap.countries',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE),
        );
    }

    public function render(): string
    {
        return Json::encode($this->countryBoundaries->geoJsonFor(
            $this->routeRepository->findSummary()->getCountries()
        ));
    }
}
