<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Http\HtmlResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

#[AsController]
final readonly class LoginLogoutRequestHandler
{
    public function __construct(
        private Environment $twig,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(path: '/admin/login', name: 'admin_login', methods: ['GET', 'POST'], priority: 10)]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->security->getUser() instanceof UserInterface) {
            return new RedirectResponse($this->urlGenerator->generate('admin_file_upload'));
        }

        return new HtmlResponse($this->twig->render('html/admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]));
    }

    #[Route(path: '/admin/logout', name: 'admin_logout', methods: ['POST'], priority: 10)]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key on the firewall.');
    }
}
