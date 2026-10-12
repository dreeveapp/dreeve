<?php

declare(strict_types=1);

namespace App\Domain\Import\DeleteFileImport;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class DeleteFileImportCommandHandler implements CommandHandler
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
        private RenderCache $renderCache,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteFileImport);

        try {
            $activityId = $this->fileImportRepository->find($command->getFileImportId())->getActivityId();
        } catch (EntityNotFound) {
            return;
        }

        $this->fileImportRepository->delete($command->getFileImportId());

        if ($activityId instanceof ActivityId) {
            $this->renderCache->invalidateTags(ActivityCacheTag::for($activityId));
        }
    }
}
