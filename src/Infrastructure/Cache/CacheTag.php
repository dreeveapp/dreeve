<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Settings\SettingsGroup;

enum CacheTag: string
{
    case ACTIVITIES = 'activities';
    case SETTINGS_GENERAL = 'settings.general';
    case SETTINGS_APPEARANCE = 'settings.appearance';
    case SETTINGS_IMPORT = 'settings.import';
    case SETTINGS_METRICS = 'settings.metrics';
    case SETTINGS_ZWIFT = 'settings.zwift';
    case SETTINGS_INTEGRATIONS = 'settings.integrations';
    case SETTINGS_DAEMON = 'settings.daemon';

    public static function forSettingsGroup(SettingsGroup $settingsGroup): self
    {
        return match ($settingsGroup) {
            SettingsGroup::GENERAL => self::SETTINGS_GENERAL,
            SettingsGroup::APPEARANCE => self::SETTINGS_APPEARANCE,
            SettingsGroup::IMPORT => self::SETTINGS_IMPORT,
            SettingsGroup::METRICS => self::SETTINGS_METRICS,
            SettingsGroup::ZWIFT => self::SETTINGS_ZWIFT,
            SettingsGroup::INTEGRATIONS => self::SETTINGS_INTEGRATIONS,
            SettingsGroup::DAEMON => self::SETTINGS_DAEMON,
        };
    }
}
