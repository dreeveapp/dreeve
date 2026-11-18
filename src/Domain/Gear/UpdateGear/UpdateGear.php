<?php

declare(strict_types=1);

namespace App\Domain\Gear\UpdateGear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearStatus;
use App\Domain\Gear\ProvideNotesFromPayload;
use App\Domain\Gear\ProvidePurchasePriceFromPayload;
use App\Domain\Image\ProvideLocalImageFromDropZonePayload;
use App\Domain\Image\RemovedImage;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use Money\Money;

final readonly class UpdateGear extends DomainCommand implements DeserializableCommand
{
    use ProvideNotesFromPayload;
    use ProvidePurchasePriceFromPayload;
    use ProvideLocalImageFromDropZonePayload;
    use ProvidesCommandName;

    private function __construct(
        private GearId $gearId,
        private string $name,
        private bool $isRetired,
        private ?Money $purchasePrice,
        private ?NewMediaItem $newMediaItem,
        private ?RemovedImage $removedMediaItem,
        private ?string $notes,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['gearId'], $payload['name'])
            || !is_string($payload['gearId'])
            || !is_string($payload['name'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "gearId" and "name" are required.');
        }

        $name = trim($payload['name']);
        if ('' === $name) {
            throw CouldNotDeserializeCommand::invalidPayload('The name cannot be empty.');
        }

        $status = $payload['status'] ?? GearStatus::ACTIVE->value;
        if (!is_string($status) || !$gearStatus = GearStatus::tryFrom($status)) {
            throw CouldNotDeserializeCommand::invalidPayload('The status is invalid.');
        }

        [$newMediaItems, $removedMediaItems] = self::parseImages($payload, 'mediaItem');

        return new self(
            gearId: GearId::fromString($payload['gearId']),
            name: $name,
            isRetired: GearStatus::RETIRED === $gearStatus,
            purchasePrice: self::parsePurchasePrice($payload),
            newMediaItem: $newMediaItems[0] ?? null,
            removedMediaItem: $removedMediaItems[0] ?? null,
            notes: self::parseNotes($payload),
        );
    }

    public function getGearId(): GearId
    {
        return $this->gearId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRetired(): bool
    {
        return $this->isRetired;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function getNewMediaItem(): ?NewMediaItem
    {
        return $this->newMediaItem;
    }

    public function getRemovedMediaItem(): ?RemovedImage
    {
        return $this->removedMediaItem;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }
}
