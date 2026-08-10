<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\Dashboard\Widget\ConfiguredWidget;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Domain\Dashboard\Widget\DependsOnCurrentDay;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;

final readonly class DashboardWidgetFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'dashboard/widget';

    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private Clock $clock,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)$#', $path, $matches)) {
            return null;
        }

        try {
            $dashboardWidgetId = DashboardWidgetId::fromString($matches[1]);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $configuredWidget = $this->configuredWidgets->find($dashboardWidgetId);
        if (!$configuredWidget instanceof ConfiguredWidget) {
            return null;
        }

        $now = $this->clock->getCurrentDateTimeImmutable();
        $widget = $configuredWidget->getWidget();

        return new ResolvedFragment(
            path: sprintf('%s/%s', self::BASE_PATH, $dashboardWidgetId),
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
                $dashboardWidgetId,
                $now,
                $configuredWidget->getConfiguration(),
            ),
            type: FragmentType::PARTIAL,
        );
    }
}
