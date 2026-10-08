<?php

declare(strict_types=1);

namespace App\Domain\Segment\DeleteSegment;

use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Import\ImportMode;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class DeleteSegmentCommandHandler implements CommandHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private ActivityScanRepository $activityScanRepository,
        private ImportMode $importMode,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteSegment);

        try {
            $segment = $this->segmentRepository->find($command->getSegmentId());
        } catch (EntityNotFound) {
            throw CouldNotProcessCommand::withReason('The segment does not exist.');
        }

        if (!$segment->isDeletableIn($this->importMode)) {
            throw CouldNotProcessCommand::withReason('Segments imported from Strava can only be deleted when running in file import mode.');
        }

        $this->segmentEffortRepository->deleteForSegment($segment->getId());
        $this->activityScanRepository->deleteForSubject(type: ActivityScanType::CUSTOM_SEGMENT, subjectId: (string) $segment->getId());

        $segment->delete();
        $this->segmentRepository->delete($segment);
    }
}
