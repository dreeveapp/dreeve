<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Heatmap;

use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class HeatmapFragment implements Fragment
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

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_ROUTE, RootCacheTag::SETTINGS_MAPS),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/heatmap.html.twig')->render([
            'summary' => $this->routeRepository->findSummary(),
            'heatmapConfig' => $this->settingsRepository->maps()->getHeatmapConfig(),
        ]);
    }
}
