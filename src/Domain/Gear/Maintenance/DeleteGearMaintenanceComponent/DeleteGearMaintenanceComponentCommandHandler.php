<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Media\MediaItemStorage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class DeleteGearMaintenanceComponentCommandHandler implements CommandHandler
{
    public function __construct(
        private GearComponentRepository $gearComponentRepository,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteGearMaintenanceComponent);

        try {
            $gearComponent = $this->gearComponentRepository->find($command->getGearComponentId());
        } catch (EntityNotFound) {
            return;
        }

        if ($this->gearComponentRepository->findAll()->hasComponentsInstalledOn($gearComponent)) {
            throw CouldNotProcessCommand::withReason('A component that has other components installed on it cannot be deleted. Retire it instead.');
        }

        $gearComponent->delete();
        $this->gearComponentRepository->delete($gearComponent);

        if ($mediaItemId = $gearComponent->getMediaItemId()) {
            $this->mediaItemStorage->remove($mediaItemId);
        }
    }
}
