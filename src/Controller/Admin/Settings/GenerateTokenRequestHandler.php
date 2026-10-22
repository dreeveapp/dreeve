<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Security\Api\ApiToken;
use App\Infrastructure\Security\Mcp\McpToken;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class GenerateTokenRequestHandler
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route(path: '/admin/settings/token/generate/{type}', name: 'admin_generate_token', requirements: ['type' => 'api|mcp'], methods: ['GET'], priority: 10)]
    public function handle(string $type): HtmlResponse
    {
        $token = match ($type) {
            'api' => ApiToken::generate(),
            'mcp' => McpToken::generate(),
            default => throw new NotFoundHttpException(),
        };

        $response = new HtmlResponse($this->twig->render(sprintf('html/admin/page/settings/token/generate-%s.html.twig', $type), [
            'key' => $token,
        ]));

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
