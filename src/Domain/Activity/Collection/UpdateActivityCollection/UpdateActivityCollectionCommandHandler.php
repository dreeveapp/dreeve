<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class UpdateActivityCollectionCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityCollectionRepository $activityCollectionRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateActivityCollection);

        $this->activityCollectionRepository->update(
            $this->activityCollectionRepository->find($command->getActivityCollectionId())
                ->withName($command->getName())
                ->withDescription($command->getDescription())
                ->withType($command->getType())
        );
    }
}
