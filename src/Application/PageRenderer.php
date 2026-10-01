<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;

final readonly class PageRenderer
{
    public function __construct(
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    /**
     * @param \Closure(): ?string $render
     */
    public function render(
        Cacheability $cacheability,
        \Closure $render,
        ?NavigationSection $navigationSection,
        ?OpenGraph $openGraph = null,
    ): HtmlResponse {
        $renderedContent = $this->cacheableRenderer->render(
            cacheability: $cacheability,
            render: $render,
        );

        $html = $this->appShell->render(
            content: $renderedContent->getContent() ?? '',
            navigationSection: $navigationSection,
            openGraph: $openGraph,
        );

        if ($cacheability->getCacheContexts()->isEmpty()) {
            return new HtmlResponse($html, headers: $renderedContent->getCacheHeaders());
        }

        return new PrivateNoStoreHtmlResponse($html, headers: $renderedContent->getCacheHeaders());
    }
}
