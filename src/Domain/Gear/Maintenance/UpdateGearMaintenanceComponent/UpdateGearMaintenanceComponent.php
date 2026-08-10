<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent;

use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\ParsesGearMaintenanceComponentPayload;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Domain\Gear\ProvidePurchasePriceFromPayload;
use App\Domain\Image\NewImage;
use App\Domain\Image\ProvideLocalImageFromDropZonePayload;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\String\Name;
use Money\Money;

final readonly class UpdateGearMaintenanceComponent extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;
    use ParsesGearMaintenanceComponentPayload;
    use ProvidePurchasePriceFromPayload;
    use ProvideLocalImageFromDropZonePayload;

    private function __construct(
        private GearComponentId $gearComponentId,
        private Name $label,
        private GearIds $attachedTo,
        private ?NewImage $newImage,
        private ?RemovedImage $removedImage,
        private ?Money $purchasePrice,
        private MaintenanceTasks $maintenanceTasks,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['gearComponentId']) || !is_string($payload['gearComponentId']) || '' === trim($payload['gearComponentId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "gearComponentId" is required.');
        }

        [$newImages, $removedImages] = self::parseImages($payload, 'localImagePath');

        return new self(
            gearComponentId: GearComponentId::fromString(trim($payload['gearComponentId'])),
            label: self::parseLabel($payload),
            attachedTo: self::parseAttachedTo($payload),
            newImage: $newImages[0] ?? null,
            removedImage: $removedImages[0] ?? null,
            purchasePrice: self::parsePurchasePrice($payload),
            maintenanceTasks: self::parseMaintenanceTasks($payload, generateMissingIds: true),
        );
    }

    public function getGearComponentId(): GearComponentId
    {
        return $this->gearComponentId;
    }

    public function getLabel(): Name
    {
        return $this->label;
    }

    public function getAttachedTo(): GearIds
    {
        return $this->attachedTo;
    }

    public function getNewImage(): ?NewImage
    {
        return $this->newImage;
    }

    public function getRemovedImage(): ?RemovedImage
    {
        return $this->removedImage;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function getMaintenanceTasks(): MaintenanceTasks
    {
        return $this->maintenanceTasks;
    }
}
