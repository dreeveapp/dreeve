<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\DeleteActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Image\ImagePath;
use App\Domain\Image\ImageStorage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class DeleteActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ImageStorage $imageStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteActivityCollection);

        $activityCollection = $this->activityCollectionRepository->find($command->getActivityCollectionId());
        $activityCollection->delete();
        $this->activityCollectionRepository->delete($activityCollection);

        if (null !== $coverImagePath = $activityCollection->getCoverImagePath()) {
            $this->imageStorage->remove(ImagePath::fromLocalImagePath($coverImagePath));
        }
    }
}
