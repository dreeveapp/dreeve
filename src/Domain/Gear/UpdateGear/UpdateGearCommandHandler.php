<?php

declare(strict_types=1);

namespace App\Domain\Gear\UpdateGear;

use App\Domain\Gear\GearRepository;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Money\Money;

final readonly class UpdateGearCommandHandler implements CommandHandler
{
    public function __construct(
        private GearRepository $gearRepository,
        private ImageStorage $imageStorage,
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

        if ($newImage instanceof NewImage) {
            $gear = $gear->withLocalImagePath(
                $this->imageStorage->store(
                    newImage: $newImage,
                    directory: ImageDirectory::GEAR
                )->toLocalImagePath()
            );
        } elseif ($removedImage instanceof RemovedImage) {
            $gear = $gear->withLocalImagePath(null);
        }

        $this->gearRepository->update($gear);

        if ($removedImage instanceof RemovedImage) {
            $this->imageStorage->remove($removedImage->getPath());
        }
    }
}
