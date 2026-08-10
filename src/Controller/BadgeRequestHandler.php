<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Infrastructure\Http\Fragment\FragmentType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class BadgeRequestHandler
{
    public function __construct(
        private FragmentRegistry $fragmentRegistry,
        private FragmentRenderer $fragmentRenderer,
    ) {
    }

    #[Route(path: '/badge/{name}.svg', name: 'badge', requirements: ['name' => '[a-z0-9\-/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $name): Response
    {
        $fragment = $this->fragmentRegistry->find('badge/'.$name);

        if (!$fragment instanceof Fragment || FragmentType::SVG !== $fragment->getType()) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $response = $this->fragmentRenderer->render($fragment, $fragment->getType());
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }
}
