<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface SettingsRepository
{
    public function find(SettingsName $name): mixed;

    /**
     * @return array<string, mixed>
     */
    public function findGroup(SettingsGroup $group): array;

    public function save(SettingsName $name, mixed $value): void;

    /**
     * @param array<string, mixed> $data
     */
    public function saveGroup(SettingsGroup $group, array $data): void;

    public function general(): GeneralSettings;

    public function appearance(): AppearanceSettings;

    public function maps(): MapsSettings;

    public function import(): ImportSettings;

    public function metrics(): MetricsSettings;

    public function zwift(): ZwiftSettings;

    public function integrations(): IntegrationsSettings;

    public function daemon(): DaemonSettings;

    public function security(): SecuritySettings;
}
