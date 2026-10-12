<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Money\Currency;
use Money\Money;

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
            $purchasePrice = null;
            if (!empty($component['purchasePrice']['amountInCents'])) {
                $purchasePrice = new Money(
                    amount: (int) $component['purchasePrice']['amountInCents'],
                    currency: new Currency($component['purchasePrice']['currency'])
                );
            }

            $gearComponent = GearComponent::create(
                id: GearComponentId::fromString((string) $component['id']),
                label: Name::fromString($component['label']),
                installations: GearComponentInstallations::fromArray(isset($component['installations'])
                    ? array_map(
                        static fn (array $installation): GearComponentInstallation => GearComponentInstallation::create(
                            gearId: GearId::fromUnprefixed($installation['gearId']),
                            from: isset($installation['from']) ? SerializableDateTime::fromString($installation['from']) : null,
                            until: isset($installation['until']) ? SerializableDateTime::fromString($installation['until']) : null,
                        ),
                        $component['installations']
                    )
                    : array_map(
                        static fn (string $gearId): GearComponentInstallation => GearComponentInstallation::create(
                            gearId: GearId::fromUnprefixed($gearId),
                            from: null,
                            until: null,
                        ),
                        $component['attachedTo'] ?? []
                    )),
                localImagePath: $component['localImagePath'] ?? null,
                purchasePrice: $purchasePrice
            );

            foreach ($component['maintenance'] ?? [] as $task) {
                $gearComponent->addMaintenanceTask(MaintenanceTask::create(
                    id: MaintenanceTaskId::fromString($task['id']),
                    label: Name::fromString($task['label']),
                    intervalValue: $task['interval']['value'],
                    intervalUnit: IntervalUnit::from($task['interval']['unit'])
                ));
            }

            $gearMaintenanceConfig->addComponent($gearComponent);
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
