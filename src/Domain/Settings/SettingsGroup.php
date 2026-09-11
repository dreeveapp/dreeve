<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum SettingsGroup: string
{
    case GENERAL = 'general';
    case APPEARANCE = 'appearance';
    case MAPS = 'maps';
    case IMPORT = 'import';
    case METRICS = 'metrics';
    case ZWIFT = 'zwift';
    case INTEGRATIONS = 'integrations';
    case DAEMON = 'daemon';
    case SECURITY = 'security';

    /**
     * @param array<string, mixed> $data
     */
    public function settingsFromArray(array $data): object
    {
        return match ($this) {
            self::GENERAL => GeneralSettings::fromArray($data),
            self::APPEARANCE => AppearanceSettings::fromArray($data),
            self::MAPS => MapsSettings::fromArray($data),
            self::IMPORT => ImportSettings::fromArray($data),
            self::METRICS => MetricsSettings::fromArray($data),
            self::ZWIFT => ZwiftSettings::fromArray($data),
            self::INTEGRATIONS => IntegrationsSettings::fromArray($data),
            self::DAEMON => DaemonSettings::fromArray($data),
            self::SECURITY => SecuritySettings::fromArray($data),
        };
    }
}
