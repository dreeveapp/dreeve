<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\CreateGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class CreateGearMaintenanceComponentCommandHandler implements CommandHandler
{
    public function __construct(
        private GearComponentRepository $gearComponentRepository,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof CreateGearMaintenanceComponent);

        $newMediaItem = $command->getNewMediaItem();
        $gearComponent = GearComponent::create(
            id: GearComponentId::random(),
            label: $command->getLabel(),
            installations: $command->getInstallations(),
            mediaItemId: $newMediaItem instanceof NewMediaItem
                ? $this->mediaItemStorage->store(
                    newMediaItem: $newMediaItem,
                    directory: MediaItemDirectory::GEAR_MAINTENANCE,
                )->getId()
                : null,
            purchasePrice: $command->getPurchasePrice(),
            isRetired: $command->isRetired(),
            notes: $command->getNotes(),
        );

        foreach ($command->getMaintenanceTasks() as $maintenanceTask) {
            $gearComponent->addMaintenanceTask($maintenanceTask);
        }

        $this->gearComponentRepository->add($gearComponent);
    }
}
