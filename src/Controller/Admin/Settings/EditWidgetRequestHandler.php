<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Dashboard\ConfigureWidget\ConfigureWidget;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\DeleteWidget\DeleteWidget;
use App\Domain\Dashboard\Widget\ConfiguredWidget;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class EditWidgetRequestHandler
{
    public function __construct(
        private Environment $twig,
        private ConfiguredWidgets $configuredWidgets,
    ) {
    }

    #[Route(path: '/admin/settings/dashboard/widget/{dashboardWidgetId}/configure', name: 'admin_configure_dashboard_widget', methods: ['GET'], priority: 10)]
    public function handleConfigure(string $dashboardWidgetId): Response
    {
        $widget = $this->configuredWidgets->find(DashboardWidgetId::fromString($dashboardWidgetId));
        if (!$widget instanceof ConfiguredWidget || !$widget->isConfigurable()) {
            throw new NotFoundHttpException('Widget not found');
        }

        return new Response($this->twig->render('html/admin/page/settings/dashboard/configure-widget.html.twig', [
            'dispatchCommand' => ConfigureWidget::getCommandName(),
            'configuredWidget' => $widget,
        ]));
    }

    #[Route(path: '/admin/settings/dashboard/widget/{dashboardWidgetId}/delete', name: 'admin_delete_dashboard_widget', methods: ['GET'], priority: 10)]
    public function handleDelete(string $dashboardWidgetId): Response
    {
        $widget = $this->configuredWidgets->find(DashboardWidgetId::fromString($dashboardWidgetId));
        if (!$widget instanceof ConfiguredWidget) {
            throw new NotFoundHttpException('Widget not found');
        }

        return new Response($this->twig->render('html/admin/page/settings/dashboard/delete-widget.html.twig', [
            'dispatchCommand' => DeleteWidget::getCommandName(),
            'widget' => $widget,
        ]));
    }
}
