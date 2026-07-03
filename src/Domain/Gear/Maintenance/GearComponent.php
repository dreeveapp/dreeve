<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
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
        private GearIds $attachedTo,
        private ?string $localImagePath,
        private ?Money $purchasePrice,
    ) {
        $this->maintenanceTasks = MaintenanceTasks::empty();
    }

    public static function create(
        GearComponentId $id,
        Name $label,
        GearIds $attachedTo,
        ?string $localImagePath,
        ?Money $purchasePrice,
    ): self {
        return new self(
            id: $id,
            label: $label,
            attachedTo: $attachedTo,
            localImagePath: $localImagePath,
            purchasePrice: $purchasePrice,
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

    public function getAttachedTo(): GearIds
    {
        return $this->attachedTo;
    }

    public function isAttachedTo(GearId $gearId): bool
    {
        return $this->getAttachedTo()->has($gearId);
    }

    public function getLocalImagePath(): ?string
    {
        return $this->localImagePath;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function getMaintenanceTasks(): MaintenanceTasks
    {
        return $this->maintenanceTasks;
    }

    public function normalizeGearIds(GearIds $normalizedGearIds): void
    {
        foreach ($this->getAttachedTo() as $gearId) {
            if ($gearId->isPrefixedWithStravaPrefix()) {
                continue;
            }

            foreach ($normalizedGearIds as $normalizedGearId) {
                // Try to match the gear id with the prefix.
                if (!$gearId->matches($normalizedGearId)) {
                    continue;
                }

                // If we found a match, we can replace it.
                $this->attachedTo->replace($gearId, $normalizedGearId);
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
            'attachedTo' => $this->attachedTo->map(
                static fn (GearId $gearId): string => $gearId->toUnprefixedString(),
            ),
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
