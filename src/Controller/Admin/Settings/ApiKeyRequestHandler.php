<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Api\Token;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ApiKeyRequestHandler
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route(path: '/admin/settings/api-key/generate', name: 'admin_generate_api_key', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        $response = new HtmlResponse($this->twig->render('html/admin/page/settings/api-key/generate-api-key.html.twig', [
            'apiKey' => Token::generate(),
        ]));

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
