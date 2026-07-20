<?php

declare(strict_types=1);

namespace App\Domain\Import\DeleteFileImport;

use App\Domain\Import\FileImportRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class DeleteFileImportCommandHandler implements CommandHandler
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteFileImport);

        $this->fileImportRepository->delete($command->getFileImportId());
    }
}
