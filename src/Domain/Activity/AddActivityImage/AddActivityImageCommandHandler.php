<?php

declare(strict_types=1);

namespace App\Domain\Activity\AddActivityImage;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Image\ImagePath;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemStorage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class AddActivityImageCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddActivityImage);

        $activityWithRawData = $this->activityRepository->findWithRawData($command->getActivityId());
        $activity = $activityWithRawData->getActivity();

        $mediaItem = $this->mediaItemStorage->storeWithId(
            mediaItemId: $command->getMediaItemId(),
            newMediaItem: $command->getNewMediaItem(),
            directory: MediaItemDirectory::ACTIVITIES,
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity->withLocalImagePaths([
                ...array_map(
                    static fn (string $path): string => ImagePath::fromLocalImagePath($path)->toLocalImagePath(),
                    $activity->getLocalImagePaths()
                ),
                $mediaItem->getPath()->toLocalPath(),
            ]),
            rawData: $activityWithRawData->getRawData(),
        ));
    }
}
