<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface SettingsRepository
{
    /**
     * @return array<string, mixed>
     */
    public function find(SettingsGroup $group): array;

    /**
     * @param array<string, mixed> $data
     */
    public function save(SettingsGroup $group, array $data): void;

    public function general(): GeneralSettings;

    public function appearance(): AppearanceSettings;

    public function import(): ImportSettings;

    public function metrics(): MetricsSettings;

    public function zwift(): ZwiftSettings;

    public function integrations(): IntegrationsSettings;

    public function daemon(): DaemonSettings;
}
