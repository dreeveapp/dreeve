<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ApiFragmentRequestHandler
{
    public function __construct(
        private FragmentRegistry $fragmentRegistry,
        private FragmentRenderer $fragmentRenderer,
    ) {
    }

    #[Route(path: '/api/fragment/{type}/{path}', name: 'api_fragment', requirements: ['type' => 'page|partial|data', 'path' => '[a-zA-Z0-9_\-/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $type, string $path): Response
    {
        $fragment = $this->fragmentRegistry->find($path);

        // A fragment is only served under the segment matching its own type, so a data fragment
        // can never be handed out as a page and the other way around.
        if (!$fragment instanceof Fragment || $fragment->getType()->value !== $type) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return $this->fragmentRenderer->render($fragment, $fragment->getType());
    }
}
