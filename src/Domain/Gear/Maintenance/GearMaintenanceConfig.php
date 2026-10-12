<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

final readonly class GearMaintenanceConfig
{
    private function __construct(
        private bool $isFeatureEnabled,
        private bool $ignoreRetiredGear,
    ) {
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public static function fromArray(
        ?array $config,
    ): self {
        return new self(
            isFeatureEnabled: (bool) ($config['enabled'] ?? false),
            ignoreRetiredGear: (bool) ($config['ignoreRetiredGear'] ?? false),
        );
    }

    public function isFeatureEnabled(): bool
    {
        return $this->isFeatureEnabled;
    }

    public function ignoreRetiredGear(): bool
    {
        return $this->ignoreRetiredGear;
    }
}
