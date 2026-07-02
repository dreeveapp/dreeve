<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class SettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route(path: '/admin/settings/dashboard', name: 'admin_settings_dashboard', methods: ['GET'], priority: 10)]
    public function handle(): Response
    {
        return new Response($this->twig->render('html/admin/page/settings/dashboard.html.twig'));
    }
}
