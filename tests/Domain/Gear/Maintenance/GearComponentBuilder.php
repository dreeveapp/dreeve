<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Media\MediaItemId;
use App\Infrastructure\ValueObject\String\Name;
use Money\Money;

final class GearComponentBuilder
{
    private GearComponentId $gearComponentId;
    private string $label = 'Chain';
    private ?MediaItemId $mediaItemId = null;
    private ?Money $purchasePrice = null;
    private bool $isRetired = false;
    private ?string $notes = null;
    /** @var GearComponentInstallation[] */
    private array $installations = [];
    /** @var MaintenanceTask[] */
    private array $maintenanceTasks = [];

    private function __construct()
    {
        $this->gearComponentId = GearComponentId::fromUnprefixed('test');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): GearComponent
    {
        $gearComponent = GearComponent::create(
            id: $this->gearComponentId,
            label: Name::fromString($this->label),
            installations: GearComponentInstallations::fromArray($this->installations),
            mediaItemId: $this->mediaItemId,
            purchasePrice: $this->purchasePrice,
            isRetired: $this->isRetired,
            notes: $this->notes,
        );
        foreach ($this->maintenanceTasks as $maintenanceTask) {
            $gearComponent->addMaintenanceTask($maintenanceTask);
        }

        return $gearComponent;
    }

    public function withGearComponentId(GearComponentId $gearComponentId): self
    {
        $this->gearComponentId = $gearComponentId;

        return $this;
    }

    public function withLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function withMediaItemId(?MediaItemId $mediaItemId): self
    {
        $this->mediaItemId = $mediaItemId;

        return $this;
    }

    public function withPurchasePrice(?Money $purchasePrice): self
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function withIsRetired(bool $isRetired): self
    {
        $this->isRetired = $isRetired;

        return $this;
    }

    public function withNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function withInstallation(GearComponentInstallation $installation): self
    {
        $this->installations[] = $installation;

        return $this;
    }

    public function withMaintenanceTask(MaintenanceTask $maintenanceTask): self
    {
        $this->maintenanceTasks[] = $maintenanceTask;

        return $this;
    }
}
