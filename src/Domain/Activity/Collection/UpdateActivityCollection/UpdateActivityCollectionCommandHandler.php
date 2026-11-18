<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Image\RemovedImage;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\ValueObject\Time\DateRange;

final readonly class UpdateActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private MediaItemStorage $mediaItemStorage,
        private ActivityRepository $activityRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateActivityCollection);

        $activityIds = ActivityIds::fromArray(array_filter([
            ...$command->getActivityIds(),
            $command->getActivityIdToAdd(),
        ]))->unique();

        if (count($this->activityRepository->findByIds($activityIds)) !== count($activityIds)) {
            throw CouldNotProcessCommand::withReason('The selected activity does not exist.');
        }

        $addActivitiesInRange = $command->getAddActivitiesInRange();
        if ($addActivitiesInRange instanceof DateRange) {
            $activityIds = $activityIds->mergeWith(ActivityIds::fromArray($this->activityRepository->findByDateRange(
                from: $addActivitiesInRange->getFrom(),
                till: $addActivitiesInRange->getTill()->modify('+1 day'),
            )->map(static fn (Activity $activity): ActivityId => $activity->getId())))->unique();
        }

        $activityCollection = $this->activityCollectionRepository->find($command->getActivityCollectionId())
            ->withName($command->getName())
            ->withDescription($command->getDescription())
            ->withType($command->getType())
            ->withActivityIds($activityIds)
            ->withUseRouteMapAsCover($command->useRouteMapAsCover());

        $newImage = $command->getNewImage();
        $removedImage = $command->getRemovedImage();

        if ($newImage instanceof NewMediaItem) {
            $activityCollection = $activityCollection->withCoverMediaItemId($this->mediaItemStorage->store(
                newMediaItem: $newImage,
                directory: MediaItemDirectory::ACTIVITY_COLLECTIONS,
            )->getId());
        } elseif ($removedImage instanceof RemovedImage) {
            $activityCollection = $activityCollection->withCoverMediaItemId(null);
        }

        $this->activityCollectionRepository->update($activityCollection);

        if ($removedImage instanceof RemovedImage) {
            $this->mediaItemStorage->remove(MediaItemId::fromString($removedImage->getId()));
        }
    }
}
