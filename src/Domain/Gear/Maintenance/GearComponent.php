<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Infrastructure\ValueObject\String\Name;
use Money\Money;

final readonly class GearComponent implements \JsonSerializable
{
    private MaintenanceTasks $maintenanceTasks;

    private function __construct(
        private GearComponentId $id,
        private Name $label,
        private GearComponentInstallations $installations,
        private ?string $localImagePath,
        private ?Money $purchasePrice,
        private bool $isRetired,
        private ?string $notes,
    ) {
        $this->maintenanceTasks = MaintenanceTasks::empty();
    }

    public static function create(
        GearComponentId $id,
        Name $label,
        GearComponentInstallations $installations,
        ?string $localImagePath,
        ?Money $purchasePrice,
        bool $isRetired,
        ?string $notes,
    ): self {
        return new self(
            id: $id,
            label: $label,
            installations: $installations,
            localImagePath: $localImagePath,
            purchasePrice: $purchasePrice,
            isRetired: $isRetired,
            notes: $notes,
        );
    }

    public function addMaintenanceTask(MaintenanceTask $task): void
    {
        $this->maintenanceTasks->add($task);
    }

    public function getId(): GearComponentId
    {
        return $this->id;
    }

    public function getLabel(): Name
    {
        return $this->label;
    }

    public function getInstallations(): GearComponentInstallations
    {
        return $this->installations;
    }

    public function getLocalImagePath(): ?string
    {
        return $this->localImagePath;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function isRetired(): bool
    {
        return $this->isRetired;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getMaintenanceTasks(): MaintenanceTasks
    {
        return $this->maintenanceTasks;
    }

    public function normalizeGearIds(GearIds $normalizedGearIds): void
    {
        foreach ($this->installations as $installation) {
            $gearId = $installation->getParent()->getGearId();
            if (!$gearId || $gearId->isPrefixedWithStravaPrefix()) {
                continue;
            }

            foreach ($normalizedGearIds as $normalizedGearId) {
                if (!$gearId->matches($normalizedGearId)) {
                    continue;
                }

                $this->installations->replace($installation, GearComponentInstallation::create(
                    parent: InstallationParent::gear($normalizedGearId),
                    from: $installation->getFrom(),
                    until: $installation->getUntil(),
                    sportTypes: $installation->getSportTypes(),
                ));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $component = [
            'id' => (string) $this->id,
            'label' => (string) $this->label,
            'localImagePath' => $this->localImagePath,
            'isRetired' => $this->isRetired,
            'notes' => $this->notes,
            'installations' => $this->installations,
            'maintenance' => $this->maintenanceTasks->map(
                static fn (MaintenanceTask $task): array => [
                    'id' => (string) $task->getId(),
                    'label' => (string) $task->getLabel(),
                    'interval' => [
                        'value' => $task->getIntervalValue(),
                        'unit' => $task->getIntervalUnit()->value,
                    ],
                ],
            ),
        ];

        if ($this->purchasePrice instanceof Money) {
            $component['purchasePrice'] = [
                'amountInCents' => (int) $this->purchasePrice->getAmount(),
                'currency' => $this->purchasePrice->getCurrency()->getCode(),
            ];
        }

        return $component;
    }
}
