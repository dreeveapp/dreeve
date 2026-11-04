<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\RemoveActivityFromCollection;

use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class RemoveActivityFromCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof RemoveActivityFromCollection);

        $this->activityCollectionRepository->update(
            $this->activityCollectionRepository->find($command->getActivityCollectionId())
                ->removeActivity($command->getActivityId())
        );
    }
}
