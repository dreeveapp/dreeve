<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class DashboardFragment implements Fragment
{
    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private Environment $twig,
    ) {
    }

    public const string PATH = 'dashboard';

    public function getPath(): string
    {
        return self::PATH;
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::DASHBOARD),
            cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/dashboard/dashboard.html.twig')->render([
            'configuredWidgets' => $this->configuredWidgets,
        ]);
    }
}
