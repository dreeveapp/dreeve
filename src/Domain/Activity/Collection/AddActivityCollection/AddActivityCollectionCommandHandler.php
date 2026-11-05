<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\AddActivityCollection;

use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\NewImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Time\Clock\Clock;

final readonly class AddActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private Clock $clock,
        private ImageStorage $imageStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddActivityCollection);

        $newImage = $command->getNewImage();
        $coverImagePath = $newImage instanceof NewImage
            ? $this->imageStorage->store(
                newImage: $newImage,
                directory: ImageDirectory::ACTIVITY_COLLECTIONS,
            )->toLocalImagePath()
            : null;

        $this->activityCollectionRepository->add(ActivityCollection::create(
            activityCollectionId: $command->getActivityCollectionId(),
            name: $command->getName(),
            description: $command->getDescription(),
            type: $command->getType(),
            activityIds: $command->getActivityIds(),
            coverImagePath: $coverImagePath,
            createdOn: $this->clock->now(),
        ));
    }
}
