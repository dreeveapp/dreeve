<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\AddActivityCollection;

use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Time\Clock\Clock;

final readonly class AddActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddActivityCollection);

        $this->activityCollectionRepository->add(ActivityCollection::create(
            activityCollectionId: $command->getActivityCollectionId(),
            name: $command->getName(),
            description: $command->getDescription(),
            type: $command->getType(),
            activityIds: ActivityIds::empty(),
            createdOn: $this->clock->now(),
        ));
    }
}
