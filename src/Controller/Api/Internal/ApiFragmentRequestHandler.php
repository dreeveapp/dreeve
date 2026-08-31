<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Application\NotFoundFragment;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Infrastructure\Http\Fragment\FragmentType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ApiFragmentRequestHandler
{
    public const string PATH_PREFIX = '/api/internal/fragment';
    public const string PATH_REQUIREMENT = '[a-zA-Z0-9_\-/]+';

    public function __construct(
        private FragmentRegistry $fragmentRegistry,
        private FragmentRenderer $fragmentRenderer,
        private NotFoundFragment $notFoundFragment,
    ) {
    }

    #[Route(path: self::PATH_PREFIX.'/{type}/{path}', name: 'api_fragment', requirements: ['type' => 'page|partial|data', 'path' => self::PATH_REQUIREMENT], methods: ['GET'], priority: 3)]
    public function handle(string $type, string $path): Response
    {
        $fragmentType = FragmentType::from($type);

        if (!($fragment = $this->fragmentRegistry->findOfType($path, $fragmentType)) instanceof \App\Infrastructure\Http\Fragment\Fragment) {
            $response = FragmentType::PAGE === $fragmentType
                ? $this->fragmentRenderer->render($this->notFoundFragment)
                : new Response('');
            $response->setStatusCode(Response::HTTP_NOT_FOUND);

            return $response;
        }

        return $this->fragmentRenderer->render($fragment, $fragmentType);
    }
}
