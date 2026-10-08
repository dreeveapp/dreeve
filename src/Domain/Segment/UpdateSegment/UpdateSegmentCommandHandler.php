<?php

declare(strict_types=1);

namespace App\Domain\Segment\UpdateSegment;

use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class UpdateSegmentCommandHandler implements CommandHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateSegment);

        try {
            $segment = $this->segmentRepository->find($command->getSegmentId());
        } catch (EntityNotFound) {
            throw CouldNotProcessCommand::withReason('The segment does not exist.');
        }

        if ($segment->getType()->isImported()) {
            throw CouldNotProcessCommand::withReason('Segments imported from Strava cannot be edited.');
        }

        $this->segmentRepository->update(
            $segment
                ->withName($command->getName())
                ->withIsFavourite($command->isFavourite())
        );
    }
}
