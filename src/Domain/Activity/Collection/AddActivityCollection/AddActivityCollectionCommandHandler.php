<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\AddActivityCollection;

use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Time\Clock\Clock;

final readonly class AddActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private Clock $clock,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddActivityCollection);

        $newImage = $command->getNewImage();
        $coverMediaItemId = $newImage instanceof NewMediaItem
            ? $this->mediaItemStorage->store(
                newMediaItem: $newImage,
                directory: MediaItemDirectory::ACTIVITY_COLLECTIONS,
            )->getId()
            : null;

        $this->activityCollectionRepository->add(ActivityCollection::create(
            activityCollectionId: $command->getActivityCollectionId(),
            name: $command->getName(),
            description: $command->getDescription(),
            type: $command->getType(),
            activityIds: $command->getActivityIds(),
            coverMediaItemId: $coverMediaItemId,
            useRouteMapAsCover: $command->useRouteMapAsCover(),
            createdOn: $this->clock->now(),
        ));
    }
}
