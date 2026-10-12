<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class UpdateGearMaintenanceComponentCommandHandler implements CommandHandler
{
    public function __construct(
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private ImageStorage $imageStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateGearMaintenanceComponent);

        // Keep the existing image unless the dropzone explicitly added or removed one.
        $existingComponent = $this->gearMaintenanceRepository->findComponent($command->getGearComponentId());
        $localImagePath = $existingComponent?->getLocalImagePath();

        $newImage = $command->getNewImage();
        $removedImage = $command->getRemovedImage();

        if ($newImage instanceof NewImage) {
            $localImagePath = $this->imageStorage->store(
                newImage: $newImage,
                directory: ImageDirectory::GEAR_MAINTENANCE
            )->toLocalImagePath();
        } elseif ($removedImage instanceof RemovedImage) {
            $localImagePath = null;
        }

        $gearComponent = GearComponent::create(
            id: $command->getGearComponentId(),
            label: $command->getLabel(),
            installations: $command->getInstallations(),
            localImagePath: $localImagePath,
            purchasePrice: $command->getPurchasePrice(),
        );

        foreach ($command->getMaintenanceTasks() as $maintenanceTask) {
            $gearComponent->addMaintenanceTask($maintenanceTask);
        }

        $this->gearMaintenanceRepository->saveComponent($gearComponent);

        if ($removedImage instanceof RemovedImage) {
            $this->imageStorage->remove($removedImage->getPath());
        }
    }
}
