<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearIds;

final readonly class GearMaintenanceConfig
{
    private GearComponents $gearComponents;

    private function __construct(
        private bool $isFeatureEnabled,
        private bool $ignoreRetiredGear,
    ) {
        $this->gearComponents = GearComponents::empty();
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public static function fromArray(
        ?array $config,
    ): self {
        if (null === $config || [] === $config) {
            return new self(
                isFeatureEnabled: false,
                ignoreRetiredGear: false,
            );
        }

        $gearMaintenanceConfig = new self(
            isFeatureEnabled: (bool) ($config['enabled'] ?? false),
            ignoreRetiredGear: (bool) ($config['ignoreRetiredGear'] ?? false),
        );

        foreach ($config['components'] ?? [] as $component) {
            $gearMaintenanceConfig->addComponent(GearComponent::fromArray($component));
        }

        return $gearMaintenanceConfig;
    }

    private function addComponent(GearComponent $component): void
    {
        $this->gearComponents->add($component);
    }

    public function ignoreRetiredGear(): bool
    {
        return $this->ignoreRetiredGear;
    }

    public function getGearComponents(): GearComponents
    {
        return $this->gearComponents;
    }

    public function normalizeGearIds(GearIds $gearIds): void
    {
        foreach ($this->getGearComponents() as $gearComponent) {
            $gearComponent->normalizeGearIds($gearIds);
        }
    }

    public function getAllReferencedGearIds(): GearIds
    {
        return $this->getGearComponents()->getAllReferencedGearIds()->unique();
    }

    public function isFeatureEnabled(): bool
    {
        return $this->isFeatureEnabled;
    }
}
