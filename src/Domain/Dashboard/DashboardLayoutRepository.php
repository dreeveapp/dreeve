<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\Dashboard\Widget\WidgetName;

interface DashboardLayoutRepository
{
    public function find(): DashboardLayout;

    public function addWidget(DashboardWidgetId $dashboardWidgetId, WidgetName $widgetName, int $width): void;

    public function deleteWidget(DashboardWidgetId $dashboardWidgetId): void;

    /**
     * @param array<string, mixed> $configuration
     */
    public function updateWidgetConfiguration(DashboardWidgetId $dashboardWidgetId, array $configuration): void;

    /**
     * @param list<array{id: string, width: int}> $orderedWidgets
     */
    public function saveLayout(array $orderedWidgets): void;

    public function resetToDefault(): void;
}
