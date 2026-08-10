<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Tag;

use App\Domain\Calendar\Month;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\ValueObject\Time\Year;

enum RootCacheTag: string implements CacheTag
{
    case APP_BUILD = 'app.build';
    case DASHBOARD = 'dashboard';
    case ACTIVITIES = 'activities';
    case ACTIVITY_IMAGES = 'activity.images';
    case ACTIVITY_ROUTE = 'activity.route';
    case SEGMENTS = 'segments';
    case CHALLENGES = 'challenges';
    case GEAR = 'gear';
    case GEAR_MAINTENANCE = 'gear.maintenance';
    case RECORDING_DEVICES = 'gear.recording-devices';
    case SETTINGS_GENERAL = 'settings.general';
    case SETTINGS_APPEARANCE = 'settings.appearance';
    case SETTINGS_MAPS = 'settings.maps';
    case SETTINGS_IMPORT = 'settings.import';
    case SETTINGS_METRICS = 'settings.metrics';
    case SETTINGS_ZWIFT = 'settings.zwift';
    case SETTINGS_INTEGRATIONS = 'settings.integrations';
    case SETTINGS_DAEMON = 'settings.daemon';

    public function toTagString(): string
    {
        return $this->value;
    }

    public function forYear(Year $year): ScopedCacheTag
    {
        return ScopedCacheTag::for($this, (string) $year);
    }

    public function forMonth(Month $month): ScopedCacheTag
    {
        return ScopedCacheTag::for($this, $month->getId());
    }

    /**
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
            SettingsGroup::MAPS => self::SETTINGS_MAPS,
            SettingsGroup::IMPORT => self::SETTINGS_IMPORT,
            SettingsGroup::METRICS => self::SETTINGS_METRICS,
            SettingsGroup::ZWIFT => self::SETTINGS_ZWIFT,
            SettingsGroup::INTEGRATIONS => self::SETTINGS_INTEGRATIONS,
            SettingsGroup::DAEMON => self::SETTINGS_DAEMON,
        };
    }
}
