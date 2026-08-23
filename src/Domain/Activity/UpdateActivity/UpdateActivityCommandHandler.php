<?php

declare(strict_types=1);

namespace App\Domain\Activity\UpdateActivity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImagePath;
use App\Domain\Image\ImageStorage;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class UpdateActivityCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ImageStorage $imageStorage,
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
            ->withDescription($command->getDescription())
            ->withDeviceName($command->getDeviceName())
            ->withGear($command->getGearId())
            ->withCalories($command->getCalories())
            ->withCommute($command->isCommute());

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
                $retainedPaths[] = $this->imageStorage->store(
                    newImage: $newImage,
                    directory: ImageDirectory::ACTIVITIES
                );
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
            $this->imageStorage->remove($path);
        }
    }
}
