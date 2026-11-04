<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class UpdateActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ImageStorage $imageStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateActivityCollection);

        $activityCollection = $this->activityCollectionRepository->find($command->getActivityCollectionId())
            ->withName($command->getName())
            ->withDescription($command->getDescription())
            ->withType($command->getType());

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
