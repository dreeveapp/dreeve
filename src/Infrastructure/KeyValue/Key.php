<?php

declare(strict_types=1);

namespace App\Infrastructure\KeyValue;

enum Key: string
{
    case THEME = 'theme';
    case APP_LAST_BUILT_ON = 'appLastBuiltOn';
    case GEAR_MAINTENANCE = 'gearMaintenance';
    case DASHBOARD = 'dashboard';
    case FORCE_REBUILD = 'forceRebuild';
    case SETTINGS_GENERAL = 'settingsGeneral';
    case SETTINGS_APPEARANCE = 'settingsAppearance';
    case SETTINGS_IMPORT = 'settingsImport';
    case SETTINGS_METRICS = 'settingsMetrics';
    case SETTINGS_ZWIFT = 'settingsZwift';
    case SETTINGS_INTEGRATIONS = 'settingsIntegrations';
    case SETTINGS_DAEMON = 'settingsDaemon';
}
