<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\DeleteActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemStorage;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\ValueObject\String\Path;

final readonly class DeleteActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private MediaItemStorage $mediaItemStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteActivityCollection);

        $activityCollection = $this->activityCollectionRepository->find($command->getActivityCollectionId());
        $activityCollection->delete();
        $this->activityCollectionRepository->delete($activityCollection);

        if (null !== $coverImagePath = $activityCollection->getCoverImagePath()) {
            $this->mediaItemStorage->remove(MediaItemId::fromUnprefixed(Path::fromString($coverImagePath)->getFilenameWithoutExtension()));
        }
    }
}
