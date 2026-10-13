<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Infrastructure\Eventing\RecordsEvents;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use Money\Money;

#[ORM\Entity]
#[ORM\Table(name: 'GearComponent')]
final class GearComponent implements \JsonSerializable
{
    use RecordsEvents;

    #[ORM\Column(type: 'json')]
    private readonly MaintenanceTasks $maintenanceTasks;

    private function __construct(
        #[ORM\Id, ORM\Column(name: 'gearComponentId', type: 'string', unique: true)]
        private readonly GearComponentId $id,
        #[ORM\Column(type: 'string')]
        private readonly Name $label,
        #[ORM\Column(type: 'json')]
        private readonly GearComponentInstallations $installations,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?string $localImagePath,
        #[ORM\Embedded(class: Money::class)]
        private readonly ?Money $purchasePrice,
        #[ORM\Column(type: 'boolean')]
        private readonly bool $isRetired,
        #[ORM\Column(type: 'text', nullable: true)]
        private readonly ?string $notes,
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
        $gearComponent = new self(
            id: $id,
            label: $label,
            installations: $installations,
            localImagePath: $localImagePath,
            purchasePrice: $purchasePrice,
            isRetired: $isRetired,
            notes: $notes,
        );
        $gearComponent->recordThat(new GearComponentWasUpdated());

        return $gearComponent;
    }

    /**
     * @param array<string, mixed> $component
     */
    public static function fromArray(array $component): self
    {
        $purchasePrice = null;
        if (!empty($component['purchasePrice']['amountInCents'])) {
            $purchasePrice = new Money(
                amount: (int) $component['purchasePrice']['amountInCents'],
                currency: new Currency($component['purchasePrice']['currency'])
            );
        }

        $gearComponent = new self(
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
        );

        foreach ($component['maintenance'] ?? [] as $task) {
            $gearComponent->addMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromString($task['id']),
                label: Name::fromString($task['label']),
                intervalValue: $task['interval']['value'],
                intervalUnit: IntervalUnit::from($task['interval']['unit'])
            ));
        }

        return $gearComponent;
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

    public function withLabel(Name $label): self
    {
        $clone = clone ($this, [
            'label' => $label,
        ]);
        if ((string) $this->label !== (string) $label) {
            $clone->recordThat(new GearComponentWasUpdated());
        }

        return $clone;
    }

    public function getInstallations(): GearComponentInstallations
    {
        return $this->installations;
    }

    public function withInstallations(GearComponentInstallations $installations): self
    {
        $clone = clone ($this, [
            'installations' => $installations,
        ]);
        if ($this->installations != $installations) {
            $clone->recordThat(new GearComponentWasUpdated());
        }

        return $clone;
    }

    public function getLocalImagePath(): ?string
    {
        return $this->localImagePath;
    }

    public function withLocalImagePath(?string $localImagePath): self
    {
        $clone = clone ($this, [
            'localImagePath' => $localImagePath,
        ]);
        if ($this->localImagePath !== $localImagePath) {
            $clone->recordThat(new GearComponentWasUpdated());
        }

        return $clone;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function withPurchasePrice(?Money $purchasePrice): self
    {
        $clone = clone ($this, [
            'purchasePrice' => $purchasePrice,
        ]);
        $isUnchanged = $this->purchasePrice instanceof Money && $purchasePrice instanceof Money
            ? $this->purchasePrice->equals($purchasePrice)
            : $this->purchasePrice === $purchasePrice;
        if (!$isUnchanged) {
            $clone->recordThat(new GearComponentWasUpdated());
        }

        return $clone;
    }

    public function isRetired(): bool
    {
        return $this->isRetired;
    }

    public function withIsRetired(bool $isRetired): self
    {
        $clone = clone ($this, [
            'isRetired' => $isRetired,
        ]);
        if ($this->isRetired !== $isRetired) {
            $clone->recordThat(new GearComponentWasUpdated());
        }

        return $clone;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function withNotes(?string $notes): self
    {
        $clone = clone ($this, [
            'notes' => $notes,
        ]);
        if ($this->notes !== $notes) {
            $clone->recordThat(new GearComponentWasUpdated());
        }

        return $clone;
    }

    public function getMaintenanceTasks(): MaintenanceTasks
    {
        return $this->maintenanceTasks;
    }

    public function withMaintenanceTasks(MaintenanceTasks $maintenanceTasks): self
    {
        $clone = clone ($this, [
            'maintenanceTasks' => $maintenanceTasks,
        ]);
        if ($this->maintenanceTasks != $maintenanceTasks) {
            $clone->recordThat(new GearComponentWasUpdated());
        }

        return $clone;
    }

    public function normalizeGearIds(GearIds $normalizedGearIds): void
    {
        foreach ($this->installations as $installation) {
            $gearId = $installation->getParent()->getGearId();
            if (!$gearId instanceof GearId || $gearId->isPrefixedWithStravaPrefix()) {
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
            'maintenance' => $this->maintenanceTasks,
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
