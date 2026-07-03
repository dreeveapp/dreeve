<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

interface DashboardLayoutRepository
{
    public function find(): DashboardLayout;

    public function deleteWidget(DashboardWidgetId $dashboardWidgetId): void;
}
