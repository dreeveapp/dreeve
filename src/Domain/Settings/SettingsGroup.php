<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use function Symfony\Component\String\u;

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
    case ATHLETE_WEIGHT = 'athleteWeight';

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom((string) u($slug)->camel());
    }

    public function getSlug(): string
    {
        return (string) u($this->value)->kebab();
    }

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
            self::ATHLETE_WEIGHT => AthleteWeightSettings::fromArray($data),
        };
    }
}
