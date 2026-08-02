<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use App\Infrastructure\Cache\CacheableRenderer;
use Symfony\Component\HttpFoundation\Response;

final readonly class PageRenderer
{
    public function __construct(
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    public function render(Page $page): Response
    {
        $render = $this->cacheableRenderer->render($page);

        $response = new Response(
            content: $render->content ?? '',
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
        );

        $render->wasServedFromCache
            ? $response->headers->set('X-Cache-Hit', $render->cacheKey)
            : $response->headers->set('X-Cache-Miss', $render->cacheKey ?? 'uncacheable');

        if (!$page->getCacheability()->getCacheContexts()->isEmpty()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
