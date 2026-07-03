<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

interface DashboardLayoutRepository
{
    public function find(): DashboardLayout;

    public function deleteWidget(DashboardWidgetId $dashboardWidgetId): void;

    /**
     * @param array<string, mixed> $configuration
     */
    public function updateWidgetConfiguration(DashboardWidgetId $dashboardWidgetId, array $configuration): void;
}
