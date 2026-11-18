<?php

declare(strict_types=1);

namespace App\Domain\Activity\UpdateActivity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\RemovedMediaItem;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class UpdateActivityCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateActivity);

        $activityWithRawData = $this->activityRepository->findWithRawData($command->getActivityId());
        $activity = $activityWithRawData
            ->getActivity()
            ->withName($command->getName())
            ->withSportType($command->getSportType())
            ->withWorkoutType($command->getWorkoutType())
            ->withDescription($command->getDescription())
            ->withDeviceName($command->getDeviceName())
            ->withGear($command->getGearId())
            ->withCalories($command->getCalories())
            ->withCommute($command->isCommute())
            ->withGroupActivity($command->isGroupActivity())
            ->withExcludedFrom($command->getExcludedFrom());

        $newMediaItems = $command->getNewMediaItems();
        $removedMediaItems = $command->getRemovedMediaItems();

        $mediaItemIdsThatNeedRemoval = [];
        if ([] !== $newMediaItems || [] !== $removedMediaItems) {
            $removedMediaItemIds = MediaItemIds::fromArray(array_map(
                static fn (RemovedMediaItem $removedMediaItem): MediaItemId => $removedMediaItem->getId(),
                $removedMediaItems
            ));

            $retainedMediaItemIds = MediaItemIds::empty();
            foreach ($activity->getMediaItemIds() as $mediaItemId) {
                if ($removedMediaItemIds->has($mediaItemId)) {
                    $mediaItemIdsThatNeedRemoval[] = $mediaItemId;
                    continue;
                }

                $retainedMediaItemIds->add($mediaItemId);
            }

            foreach ($newMediaItems as $newMediaItem) {
                $retainedMediaItemIds->add($this->mediaItemStorage->store(
                    newMediaItem: $newMediaItem,
                    directory: MediaItemDirectory::ACTIVITIES,
                )->getId());
            }

            $activity = $activity->withMediaItemIds($retainedMediaItemIds);
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $activityWithRawData->getRawData(),
        ));

        foreach ($mediaItemIdsThatNeedRemoval as $mediaItemId) {
            $this->mediaItemStorage->remove($mediaItemId);
        }
    }
}
