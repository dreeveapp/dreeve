<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Settings\SettingsGroup;

enum CacheTag: string
{
    case APP_BUILD = 'app.build';
    case ACTIVITIES = 'activities';
    case ACTIVITY_IMAGES = 'activity.images';
    case SEGMENTS = 'segments';
    case CHALLENGES = 'challenges';
    case SETTINGS_GENERAL = 'settings.general';
    case SETTINGS_APPEARANCE = 'settings.appearance';
    case SETTINGS_IMPORT = 'settings.import';
    case SETTINGS_METRICS = 'settings.metrics';
    case SETTINGS_ZWIFT = 'settings.zwift';
    case SETTINGS_INTEGRATIONS = 'settings.integrations';
    case SETTINGS_DAEMON = 'settings.daemon';

    /**
     * Settings that feed formatting and athlete context used by virtually every render:
     * unit system, locale and date format; athlete, heart rate zones, FTP and weight history.
     * Every cacheable depends on them, so they are applied to every Cacheability instead of
     * being declared per component, where they could be forgotten.
     *
     * @return self[]
     */
    public static function crossCutting(): array
    {
        return [self::SETTINGS_APPEARANCE, self::SETTINGS_GENERAL];
    }

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
