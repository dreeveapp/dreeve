<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\DeleteGearMaintenanceComponent;

use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Image\ImagePath;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;
use League\Flysystem\FilesystemOperator;

final readonly class DeleteGearMaintenanceComponentCommandHandler implements CommandHandler
{
    public function __construct(
        private GearComponentRepository $gearComponentRepository,
        private FilesystemOperator $fileStorage,
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

        $gearComponent->delete();
        $this->gearComponentRepository->delete($gearComponent);

        if (null !== $localImagePath = $gearComponent->getLocalImagePath()) {
            $fileSystemPath = ImagePath::fromLocalImagePath($localImagePath)->toFileSystemPath();
            if ($this->fileStorage->fileExists($fileSystemPath)) {
                $this->fileStorage->delete($fileSystemPath);
            }
        }
    }
}
