<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Infrastructure\ValueObject\Identifier\Identifier;

final readonly class DashboardWidgetId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'dashboardWidget-';
    }
}
