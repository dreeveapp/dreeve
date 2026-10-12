<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
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
                installations: GearComponentInstallations::fromArray(array_map(
                    static fn (array $installation): GearComponentInstallation => GearComponentInstallation::create(
                        parent: InstallationParent::fromString($installation['parent']),
                        from: isset($installation['from']) ? SerializableDateTime::fromString($installation['from']) : null,
                        until: isset($installation['until']) ? SerializableDateTime::fromString($installation['until']) : null,
                        sportTypes: SportTypes::fromArray(array_map(SportType::from(...), $installation['sportTypes'])),
                    ),
                    $component['installations']
                )),
                localImagePath: $component['localImagePath'] ?? null,
                purchasePrice: $purchasePrice,
                isRetired: (bool) $component['isRetired'],
                notes: $component['notes'],
                startingValue: isset($component['startingValue']) ? StartingValue::create(
                    distance: Meter::from($component['startingValue']['distanceInMeter']),
                    movingTime: Seconds::from($component['startingValue']['movingTimeInSeconds']),
                ) : null,
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
