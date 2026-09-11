<?php

declare(strict_types=1);

namespace App\Infrastructure\KeyValue;

enum Key: string
{
    case THEME = 'theme';
    case GEAR_MAINTENANCE = 'gearMaintenance';
    case DASHBOARD = 'dashboard';
    case AUTOMATION_RULES_BACKFILL = 'automationRulesBackfill';
}
