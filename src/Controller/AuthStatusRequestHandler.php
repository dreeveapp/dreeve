<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AuthStatusRequestHandler
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[Route(path: '/auth/status', name: 'auth_status', methods: ['GET'], priority: 2)]
    public function handle(): JsonResponse
    {
        $response = new JsonResponse(['isAuthenticated' => $this->security->isGranted('ROLE_ADMIN')]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
