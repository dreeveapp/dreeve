<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Dashboard\AddWidget\AddWidget;
use App\Domain\Dashboard\ResetDashboardLayoutToDefault\ResetDashboardLayoutToDefault;
use App\Domain\Dashboard\SaveDashboardLayout\SaveDashboardLayout;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class DashboardSettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        private ConfiguredWidgets $configuredWidgets,
    ) {
    }

    #[Route(path: '/admin/settings/dashboard', name: 'admin_settings_dashboard', methods: ['GET'], priority: 10)]
    public function handle(): Response
    {
        return new Response($this->twig->render('html/admin/page/settings/dashboard/dashboard.html.twig', [
            'widgets' => $this->configuredWidgets,
            'widths' => ConfiguredWidgets::WIDTHS,
            'addWidgetCommand' => AddWidget::getCommandName(),
            'saveLayoutCommand' => SaveDashboardLayout::getCommandName(),
        ]));
    }

    #[Route(path: '/admin/settings/dashboard/reset', name: 'admin_reset_dashboard_layout', methods: ['GET'], priority: 10)]
    public function handleReset(): Response
    {
        return new Response($this->twig->render('html/admin/page/settings/dashboard/reset-dashboard-layout.html.twig', [
            'dispatchCommand' => ResetDashboardLayoutToDefault::getCommandName(),
        ]));
    }
}
