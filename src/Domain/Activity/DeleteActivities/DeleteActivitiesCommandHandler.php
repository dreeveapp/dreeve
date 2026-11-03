<?php

declare(strict_types=1);

namespace App\Domain\Activity\DeleteActivities;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class DeleteActivitiesCommandHandler implements CommandHandler
{
    /**
     * @param iterable<DeletesActivityData> $activityDataRepositories
     */
    public function __construct(
        private ActivityRepository $activityRepository,
        private SegmentRepository $segmentRepository,
        #[AutowireIterator('app.activity.deletes_activity_data')]
        private iterable $activityDataRepositories,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteActivities);

        foreach ($command->getActivityIds() as $activityId) {
            $activity = $this->activityRepository->find($activityId);

            foreach ($this->activityDataRepositories as $activityDataRepository) {
                $activityDataRepository->deleteForActivity($activityId);
            }

            $activity->delete();
            $this->activityRepository->delete($activity);
        }

        $this->segmentRepository->deleteOrphanedImported();
    }
}
