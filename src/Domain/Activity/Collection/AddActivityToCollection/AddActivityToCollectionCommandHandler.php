<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\AddActivityToCollection;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;

final readonly class AddActivityToCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private ActivityRepository $activityRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddActivityToCollection);

        if (!$this->activityRepository->exists($command->getActivityId())) {
            throw CouldNotProcessCommand::withReason('The selected activity does not exist.');
        }

        $this->activityCollectionRepository->update(
            $this->activityCollectionRepository->find($command->getActivityCollectionId())
                ->addActivity($command->getActivityId())
        );
    }
}
