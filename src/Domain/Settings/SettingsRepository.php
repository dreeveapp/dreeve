<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface SettingsRepository
{
    /**
     * @return array<string, mixed> keyed, nested settings for the group
     */
    public function find(SettingsGroup $group): array;

    /**
     * @param array<string, mixed> $data
     */
    public function save(SettingsGroup $group, array $data): void;
}
