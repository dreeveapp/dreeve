<?php

declare(strict_types=1);

namespace App\Domain\Gear\UpdateGear;

use App\Domain\Gear\GearRepository;
use App\Domain\Media\DropZone\RemovedMediaItem;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Money\Money;

final readonly class UpdateGearCommandHandler implements CommandHandler
{
    public function __construct(
        private GearRepository $gearRepository,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateGear);

        $gear = $this->gearRepository->find($command->getGearId())
            ->withName($command->getName())
            ->withIsRetired($command->isRetired())
            ->withNotes($command->getNotes());

        if ($command->getPurchasePrice() instanceof Money) {
            $gear = $gear->withPurchasePrice($command->getPurchasePrice());
        }

        $newMediaItem = $command->getNewMediaItem();
        $removedMediaItem = $command->getRemovedMediaItem();

        if ($newMediaItem instanceof NewMediaItem) {
            $gear = $gear->withMediaItemId($this->mediaItemStorage->store(
                newMediaItem: $newMediaItem,
                directory: MediaItemDirectory::GEAR,
            )->getId());
        } elseif ($removedMediaItem instanceof RemovedMediaItem) {
            $gear = $gear->withMediaItemId(null);
        }

        $this->gearRepository->update($gear);

        if ($removedMediaItem instanceof RemovedMediaItem) {
            $this->mediaItemStorage->remove($removedMediaItem->getId());
        }
    }
}
