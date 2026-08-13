<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class AdminNotFoundRequestHandler
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route(path: '/admin/{wildcard}', name: 'admin_not_found', requirements: ['wildcard' => '.*'], methods: ['GET'], priority: 1)]
    public function handle(): HtmlResponse
    {
        $response = new HtmlResponse($this->twig->render('html/admin/page/not-found.html.twig'));
        $response->setStatusCode(Response::HTTP_NOT_FOUND);

        return $response;
    }
}
