<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\UpdateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallationLoopDetected;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class UpdateGearMaintenanceComponentCommandHandler implements CommandHandler
{
    public function __construct(
        private GearComponentRepository $gearComponentRepository,
        private ImageStorage $imageStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateGearMaintenanceComponent);

        try {
            $gearComponent = $this->gearComponentRepository->find($command->getGearComponentId());
        } catch (EntityNotFound) {
            throw CouldNotProcessCommand::withReason('The component does not exist.');
        }

        $gearComponent = $gearComponent
            ->withLabel($command->getLabel())
            ->withInstallations($command->getInstallations())
            ->withPurchasePrice($command->getPurchasePrice())
            ->withIsRetired($command->isRetired())
            ->withNotes($command->getNotes())
            ->withMaintenanceTasks($command->getMaintenanceTasks());

        $newImage = $command->getNewImage();
        $removedImage = $command->getRemovedImage();

        if ($newImage instanceof NewImage) {
            $gearComponent = $gearComponent->withLocalImagePath(
                $this->imageStorage->store(
                    newImage: $newImage,
                    directory: ImageDirectory::GEAR_MAINTENANCE
                )->toLocalImagePath()
            );
        } elseif ($removedImage instanceof RemovedImage) {
            $gearComponent = $gearComponent->withLocalImagePath(null);
        }

        $gearComponents = $this->gearComponentRepository->findAll()
            ->filter(static fn (GearComponent $otherComponent): bool => (string) $otherComponent->getId() !== (string) $gearComponent->getId())
            ->add($gearComponent);
        try {
            $gearComponents->resolveUsageWindowsFor($gearComponent);
        } catch (GearComponentInstallationLoopDetected) {
            throw CouldNotProcessCommand::withReason('A component cannot be installed on itself or on one of its own parts.');
        }

        $this->gearComponentRepository->update($gearComponent);

        if ($removedImage instanceof RemovedImage) {
            $this->imageStorage->remove($removedImage->getPath());
        }
    }
}
