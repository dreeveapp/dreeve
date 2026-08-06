<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use App\Infrastructure\Cache\Cacheable;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Http\HtmlResponse;

final readonly class PageRenderer
{
    public function __construct(
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    public function render(Cacheable $page): HtmlResponse
    {
        $render = $this->cacheableRenderer->render($page);

        $response = new HtmlResponse($render->getContent() ?? '');

        $response->headers->add($render->getCacheHeaders());

        if (!$page->getCacheability()->getCacheContexts()->isEmpty()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
