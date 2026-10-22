<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Release;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use App\Infrastructure\Security\Visitor;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ReleaseAnnouncementRequestHandler
{
    public function __construct(
        private Visitor $visitor,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/internal/release-announcement', name: 'release_announcement', methods: ['GET'])]
    public function handle(): PrivateNoStoreHtmlResponse
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'app.release-announcement',
                cacheTags: CacheTags::empty(),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->visitor->isAuthenticated()
                ? $this->twig->load('html/release-announcement.html.twig')->render()
                : '',
        );

        return new PrivateNoStoreHtmlResponse($render->getContent() ?? '', headers: $render->getCacheHeaders());
    }
}
