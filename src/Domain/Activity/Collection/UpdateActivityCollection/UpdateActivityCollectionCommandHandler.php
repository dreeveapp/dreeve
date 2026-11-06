<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;

final readonly class UpdateActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ImageStorage $imageStorage,
        private ActivityRepository $activityRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateActivityCollection);

        if (count($this->activityRepository->findByIds($command->getActivityIds())) !== count($command->getActivityIds())) {
            throw CouldNotProcessCommand::withReason('The selected activity does not exist.');
        }

        $activityCollection = $this->activityCollectionRepository->find($command->getActivityCollectionId())
            ->withName($command->getName())
            ->withDescription($command->getDescription())
            ->withType($command->getType())
            ->withActivityIds($command->getActivityIds());

        $newImage = $command->getNewImage();
        $removedImage = $command->getRemovedImage();

        if ($newImage instanceof NewImage) {
            $activityCollection = $activityCollection->withCoverImagePath(
                $this->imageStorage->store(
                    newImage: $newImage,
                    directory: ImageDirectory::ACTIVITY_COLLECTIONS,
                )->toLocalImagePath()
            );
        } elseif ($removedImage instanceof RemovedImage) {
            $activityCollection = $activityCollection->withCoverImagePath(null);
        }

        $this->activityCollectionRepository->update($activityCollection);

        if ($removedImage instanceof RemovedImage) {
            $this->imageStorage->remove($removedImage->getPath());
        }
    }
}
