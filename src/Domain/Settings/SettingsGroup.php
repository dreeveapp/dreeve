<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\KeyValue\Key;

enum SettingsGroup: string
{
    case GENERAL = 'general';
    case APPEARANCE = 'appearance';
    case IMPORT = 'import';
    case METRICS = 'metrics';
    case ZWIFT = 'zwift';
    case INTEGRATIONS = 'integrations';
    case DAEMON = 'daemon';

    public function keyValueKey(): Key
    {
        return match ($this) {
            self::GENERAL => Key::SETTINGS_GENERAL,
            self::APPEARANCE => Key::SETTINGS_APPEARANCE,
            self::IMPORT => Key::SETTINGS_IMPORT,
            self::METRICS => Key::SETTINGS_METRICS,
            self::ZWIFT => Key::SETTINGS_ZWIFT,
            self::INTEGRATIONS => Key::SETTINGS_INTEGRATIONS,
            self::DAEMON => Key::SETTINGS_DAEMON,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public function settingsFromArray(array $data): object
    {
        return match ($this) {
            self::GENERAL => GeneralSettings::fromArray($data),
            self::APPEARANCE => AppearanceSettings::fromArray($data),
            self::IMPORT => ImportSettings::fromArray($data),
            self::METRICS => MetricsSettings::fromArray($data),
            self::ZWIFT => ZwiftSettings::fromArray($data),
            self::INTEGRATIONS => IntegrationsSettings::fromArray($data),
            self::DAEMON => DaemonSettings::fromArray($data),
        };
    }
}
