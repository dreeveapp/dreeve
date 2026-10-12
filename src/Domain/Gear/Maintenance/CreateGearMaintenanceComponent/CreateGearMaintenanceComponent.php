<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\ParsesGearMaintenanceComponentPayload;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Domain\Gear\ProvidePurchasePriceFromPayload;
use App\Domain\Image\NewImage;
use App\Domain\Image\ProvideLocalImageFromDropZonePayload;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\String\Name;
use Money\Money;

final readonly class CreateGearMaintenanceComponent extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;
    use ParsesGearMaintenanceComponentPayload;
    use ProvidePurchasePriceFromPayload;
    use ProvideLocalImageFromDropZonePayload;

    private function __construct(
        private Name $label,
        private GearComponentInstallations $installations,
        private ?NewImage $newImage,
        private ?Money $purchasePrice,
        private MaintenanceTasks $maintenanceTasks,
        private bool $isRetired,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        [$newImages] = self::parseImages($payload, 'localImagePath');

        return new self(
            label: self::parseLabel($payload),
            installations: self::parseInstallations($payload),
            newImage: $newImages[0] ?? null,
            purchasePrice: self::parsePurchasePrice($payload),
            maintenanceTasks: self::parseMaintenanceTasks($payload, generateMissingIds: true),
            isRetired: filter_var($payload['isRetired'] ?? false, FILTER_VALIDATE_BOOLEAN),
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

    public function getNewImage(): ?NewImage
    {
        return $this->newImage;
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
}
