<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Http\HtmlResponse;

final readonly class PageRenderer
{
    public function __construct(
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    public function render(Page $page): HtmlResponse
    {
        $render = $this->cacheableRenderer->render($page);

        $response = new HtmlResponse($render->getContent() ?? '');

        $render->wasServedFromCache()
            ? $response->headers->set('X-Cache-Hit', $render->getCacheKey())
            : $response->headers->set('X-Cache-Miss', $render->getCacheKey() ?? 'uncacheable');

        if (!$page->getCacheability()->getCacheContexts()->isEmpty()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
