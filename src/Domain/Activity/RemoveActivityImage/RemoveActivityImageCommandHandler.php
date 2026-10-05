<?php

declare(strict_types=1);

namespace App\Domain\Activity\RemoveActivityImage;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Image\ActivityImageId;
use App\Domain\Image\ImagePath;
use App\Domain\Image\ImageStorage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class RemoveActivityImageCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ImageStorage $imageStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof RemoveActivityImage);

        $activityWithRawData = $this->activityRepository->findWithRawData($command->getActivityId());
        $activity = $activityWithRawData->getActivity();

        $paths = array_map(
            ImagePath::fromLocalImagePath(...),
            $activity->getLocalImagePaths()
        );
        $isRemoved = static fn (ImagePath $path): bool => (string) ActivityImageId::fromImagePath($path) === (string) $command->getActivityImageId();

        $removedPaths = array_values(array_filter($paths, $isRemoved));
        if ([] === $removedPaths) {
            throw new EntityNotFound(sprintf('Image "%s" not found', $command->getActivityImageId()));
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity->withLocalImagePaths(array_values(array_map(
                static fn (ImagePath $path): string => $path->toLocalImagePath(),
                array_filter($paths, static fn (ImagePath $path): bool => !$isRemoved($path))
            ))),
            rawData: $activityWithRawData->getRawData(),
        ));

        foreach ($removedPaths as $path) {
            $this->imageStorage->remove($path);
        }
    }
}
