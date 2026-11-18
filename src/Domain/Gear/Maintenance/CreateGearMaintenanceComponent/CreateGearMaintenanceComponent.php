<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\ProvideGearMaintenanceComponentPayload;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Domain\Gear\ProvideNotesFromPayload;
use App\Domain\Gear\ProvidePurchasePriceFromPayload;
use App\Domain\Media\NewMediaItem;
use App\Domain\Media\ProvideMediaItemsFromDropZonePayload;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\String\Name;
use Money\Money;

final readonly class CreateGearMaintenanceComponent extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;
    use ProvideGearMaintenanceComponentPayload;
    use ProvideNotesFromPayload;
    use ProvidePurchasePriceFromPayload;
    use ProvideMediaItemsFromDropZonePayload;

    private function __construct(
        private Name $label,
        private GearComponentInstallations $installations,
        private ?NewMediaItem $newMediaItem,
        private ?Money $purchasePrice,
        private MaintenanceTasks $maintenanceTasks,
        private bool $isRetired,
        private ?string $notes,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        [$newMediaItems] = self::parseMediaItems($payload, 'mediaItem');

        return new self(
            label: self::parseLabel($payload),
            installations: self::parseInstallations($payload),
            newMediaItem: $newMediaItems[0] ?? null,
            purchasePrice: self::parsePurchasePrice($payload),
            maintenanceTasks: self::parseMaintenanceTasks($payload, generateMissingIds: true),
            isRetired: filter_var($payload['isRetired'] ?? false, FILTER_VALIDATE_BOOLEAN),
            notes: self::parseNotes($payload),
        );
    }

    public function getLabel(): Name
    {
        return $this->label;
    }

    public function getInstallations(): GearComponentInstallations
    {
        return $this->installations;
    }

    public function getNewMediaItem(): ?NewMediaItem
    {
        return $this->newMediaItem;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function getMaintenanceTasks(): MaintenanceTasks
    {
        return $this->maintenanceTasks;
    }

    public function isRetired(): bool
    {
        return $this->isRetired;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }
}
