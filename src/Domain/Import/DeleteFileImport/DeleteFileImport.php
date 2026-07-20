<?php

declare(strict_types=1);

namespace App\Domain\Import\DeleteFileImport;

use App\Domain\Import\FileImportId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteFileImport extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private FileImportId $fileImportId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['fileImportId']) || !is_string($payload['fileImportId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "fileImportId" is required.');
        }

        return new self(
            fileImportId: FileImportId::fromString($payload['fileImportId']),
        );
    }

    public function getFileImportId(): FileImportId
    {
        return $this->fileImportId;
    }
}
