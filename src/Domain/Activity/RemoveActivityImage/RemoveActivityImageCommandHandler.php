<?php

declare(strict_types=1);

namespace App\Domain\Activity\RemoveActivityImage;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Media\MediaItemStorage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class RemoveActivityImageCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof RemoveActivityImage);

        $activityWithRawData = $this->activityRepository->findWithRawData($command->getActivityId());
        $activity = $activityWithRawData->getActivity();

        $mediaItemId = $command->getMediaItemId();
        if (!$activity->getMediaItemIds()->has($mediaItemId)) {
            throw new EntityNotFound(sprintf('Image "%s" not found', $mediaItemId));
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity->withMediaItemIds($activity->getMediaItemIds()->without($mediaItemId)),
            rawData: $activityWithRawData->getRawData(),
        ));

        $this->mediaItemStorage->remove($mediaItemId);
    }
}
