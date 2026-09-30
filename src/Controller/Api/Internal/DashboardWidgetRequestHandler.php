<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\ConfiguredWidget;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Domain\Dashboard\Widget\DependsOnCurrentDay;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class DashboardWidgetRequestHandler
{
    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private Clock $clock,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/dashboard/widget/{dashboardWidgetId}', name: 'dashboard_widget', requirements: ['dashboardWidgetId' => 'dashboardWidget-[^/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $dashboardWidgetId): Response
    {
        $dashboardWidgetId = DashboardWidgetId::fromString($dashboardWidgetId);

        $configuredWidget = $this->configuredWidgets->find($dashboardWidgetId);
        if (!$configuredWidget instanceof ConfiguredWidget) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $now = $this->clock->getCurrentDateTimeImmutable();
        $widget = $configuredWidget->getWidget();

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf(
                    'dashboard.widget.%s.%s',
                    $dashboardWidgetId,
                    sha1(Json::encode($configuredWidget->getConfiguration()->toArray())),
                ),
                cacheTags: $widget->getCacheTags(),
                ttlInSeconds: $widget instanceof DependsOnCurrentDay ? $now->getSecondsUntilMidnight() : null,
            ),
            render: fn (): ?string => $widget->render(
                dashboardWidgetId: $dashboardWidgetId,
                now: $now,
                configuration: $configuredWidget->getConfiguration(),
            ),
        );

        return new HtmlResponse($render->getContent() ?? '', headers: $render->getCacheHeaders());
    }
}
