<?php

declare(strict_types=1);

namespace App\Domain\Import\DeleteFileImport;

use App\Domain\Import\FileImportRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class DeleteFileImportCommandHandler implements CommandHandler
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteFileImport);

        try {
            $fileImport = $this->fileImportRepository->find($command->getFileImportId());
        } catch (EntityNotFound) {
            return;
        }

        $fileImport->delete();
        $this->fileImportRepository->delete($fileImport);
    }
}
