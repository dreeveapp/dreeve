<?php

declare(strict_types=1);

namespace App\Domain\Activity\UpdateActivity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Image\ImagePath;
use App\Domain\Image\RemovedImage;
use App\Domain\Media\MediaItemDirectory;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\ValueObject\String\Path;

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

        $newImages = $command->getNewImages();
        $removedImages = $command->getRemovedImages();

        $imagePathsThatNeedRemoval = [];
        if ([] !== $newImages || [] !== $removedImages) {
            $removedLocalImagePaths = array_map(
                static fn (RemovedImage $removedImage): string => $removedImage->getPath()->toLocalImagePath(),
                $removedImages
            );
            $isRemoved = static fn (ImagePath $path): bool => in_array($path->toLocalImagePath(), $removedLocalImagePaths, true);

            $currentPaths = array_map(
                ImagePath::fromLocalImagePath(...),
                $activity->getLocalImagePaths()
            );

            $retainedPaths = array_values(array_filter($currentPaths, static fn (ImagePath $path): bool => !$isRemoved($path)));

            // Only files that were genuinely attached to this activity may be deleted from disk.
            $imagePathsThatNeedRemoval = array_values(array_filter($currentPaths, $isRemoved));

            foreach ($newImages as $newImage) {
                $retainedPaths[] = ImagePath::fromLocalImagePath($this->mediaItemStorage->store(
                    newMediaItem: new NewMediaItem(
                        filename: $newImage->getFilename(),
                        content: $newImage->getContent(),
                    ),
                    directory: MediaItemDirectory::ACTIVITIES,
                )->getPath()->toLocalPath());
            }

            $activity = $activity->withLocalImagePaths(array_map(
                static fn (ImagePath $path): string => $path->toLocalImagePath(),
                $retainedPaths
            ));
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $activityWithRawData->getRawData(),
        ));

        foreach ($imagePathsThatNeedRemoval as $path) {
            $this->mediaItemStorage->remove(MediaItemId::fromUnprefixed(Path::fromString($path->toLocalImagePath())->getFilenameWithoutExtension()));
        }
    }
}
