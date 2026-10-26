<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Security\Oidc\OidcConfig;
use App\Infrastructure\Security\Oidc\OidcLoginAttempt;
use App\Infrastructure\Security\Oidc\OidcProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class OidcLoginRequestHandler
{
    public function __construct(
        private OidcConfig $oidcConfig,
        private OidcProvider $oidcProvider,
    ) {
    }

    #[Route(path: '/admin/login/oidc', name: 'admin_login_oidc', methods: ['GET'], priority: 10)]
    public function login(Request $request): Response
    {
        if (!$this->oidcConfig->isEnabled()) {
            throw new NotFoundHttpException('Not found');
        }

        $loginAttempt = OidcLoginAttempt::start();
        $request->getSession()->set(OidcLoginAttempt::SESSION_KEY, $loginAttempt);

        return new RedirectResponse($this->oidcProvider->buildAuthorizationUrl($loginAttempt));
    }

    #[Route(path: '/admin/login/oidc/callback', name: 'admin_login_oidc_callback', methods: ['GET'], priority: 10)]
    public function callback(): never
    {
        throw new NotFoundHttpException('Not found');
    }
}
