<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class CreateGearMaintenanceComponentCommandHandler implements CommandHandler
{
    public function __construct(
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private ImageStorage $imageStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof CreateGearMaintenanceComponent);

        $localImagePath = null;
        $newImage = $command->getNewImage();
        if ($newImage instanceof NewImage) {
            $localImagePath = $this->imageStorage->store(
                newImage: $newImage,
                directory: ImageDirectory::GEAR_MAINTENANCE
            )->toLocalImagePath();
        }

        $gearComponent = GearComponent::create(
            id: GearComponentId::random(),
            label: $command->getLabel(),
            installations: $command->getInstallations(),
            localImagePath: $localImagePath,
            purchasePrice: $command->getPurchasePrice(),
            isRetired: $command->isRetired(),
            notes: $command->getNotes(),
            startingValue: null,
        );

        foreach ($command->getMaintenanceTasks() as $maintenanceTask) {
            $gearComponent->addMaintenanceTask($maintenanceTask);
        }

        $this->gearMaintenanceRepository->saveComponent($gearComponent);
    }
}
