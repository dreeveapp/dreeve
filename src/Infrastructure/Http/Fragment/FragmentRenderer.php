<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

use App\Infrastructure\Cache\Cacheable;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Render\CacheStatus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class FragmentRenderer
{
    public function __construct(
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    public function render(Cacheable $cacheable, FragmentType $type = FragmentType::PAGE): Response
    {
        $render = $this->cacheableRenderer->render($cacheable);

        $response = match ($type) {
            FragmentType::PAGE, FragmentType::PARTIAL => new HtmlResponse($render->getContent() ?? ''),
            FragmentType::DATA => new JsonResponse($render->getContent() ?? '[]', json: true),
        };

        $response->headers->add($render->getCacheHeaders());

        if (!$cacheable->getCacheability()->getCacheContexts()->isEmpty()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        if (CacheStatus::UNCACHEABLE === $render->getCacheStatus()) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
