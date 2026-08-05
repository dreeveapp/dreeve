<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Infrastructure\Http\Page\PageRegistry;
use App\Infrastructure\Http\Page\PageRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ApiPageRequestHandler
{
    public function __construct(
        private PageRegistry $pageRegistry,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/api/page/{page}', name: 'api_page', requirements: ['page' => '[a-zA-Z0-9_\-/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $page): Response
    {
        if (!$registeredPage = $this->pageRegistry->find($page)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return $this->pageRenderer->render($registeredPage);
    }
}
