<?php

declare(strict_types=1);

namespace App\Domain\Gear\UpdateGear;

use App\Domain\Gear\GearRepository;
use App\Domain\Image\RemovedImage;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\ValueObject\String\Path;
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

        $newImage = $command->getNewImage();
        $removedImage = $command->getRemovedImage();

        if ($newImage instanceof NewMediaItem) {
            $gear = $gear->withLocalImagePath(ltrim($this->mediaItemStorage->store(
                newMediaItem: $newImage,
                directory: MediaItemDirectory::GEAR,
            )->getPath()->toUrlPath(), '/'));
        } elseif ($removedImage instanceof RemovedImage) {
            $gear = $gear->withLocalImagePath(null);
        }

        $this->gearRepository->update($gear);

        if ($removedImage instanceof RemovedImage) {
            $this->mediaItemStorage->remove(MediaItemId::fromUnprefixed(Path::fromString($removedImage->getId())->getFilenameWithoutExtension()));
        }
    }
}
