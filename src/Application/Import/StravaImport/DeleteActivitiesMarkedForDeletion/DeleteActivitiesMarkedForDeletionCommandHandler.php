<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\DeleteActivitiesMarkedForDeletion;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivitySummaryRepository;
use App\Domain\Activity\DeleteActivities\DeleteActivities;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class DeleteActivitiesMarkedForDeletionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private ActivitySummaryRepository $activitySummaryRepository,
        private CommandBus $commandBus,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteActivitiesMarkedForDeletion);

        $activityIdsToDelete = $this->activityIdRepository->findMarkedForDeletion();
        if ($activityIdsToDelete->isEmpty()) {
            $command->getOutput()->writeln('No activities marked for deletion...');

            return;
        }

        $command->getOutput()->writeln('Deleting activities...');

        $activitiesToDelete = [];
        foreach ($activityIdsToDelete as $activityId) {
            $activitiesToDelete[] = $this->activitySummaryRepository->find($activityId);
        }

        $this->commandBus->dispatch(new DeleteActivities($activityIdsToDelete));

        foreach ($activitiesToDelete as $activity) {
            $command->getOutput()->writeln(sprintf(
                '  => Activity "%s - %s" deleted',
                $activity->getName(),
                $activity->getStartDate()->format('d-m-Y'))
            );
        }
    }
}
