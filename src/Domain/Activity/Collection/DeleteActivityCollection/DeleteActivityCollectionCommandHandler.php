<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\DeleteActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class DeleteActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteActivityCollection);

        $activityCollection = $this->activityCollectionRepository->find($command->getActivityCollectionId());
        $activityCollection->delete();
        $this->activityCollectionRepository->delete($activityCollection);
    }
}
