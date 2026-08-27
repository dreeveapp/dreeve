<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\String\CompressedString;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DbalFileImportRepository extends DbalRepository implements FileImportRepository
{
    public function add(FileImport $fileImport): void
    {
        $sql = 'INSERT INTO FileImport (fileImportId, originalFilename, fileContents, source, status, errorMessage, activityId, importedOn)
        VALUES (:fileImportId, :originalFilename, :fileContents, :source, :status, :errorMessage, :activityId, :importedOn)';

        $this->connection->executeStatement($sql, [
            'fileImportId' => (string) $fileImport->getId(),
            'originalFilename' => $fileImport->getOriginalFilename(),
            'fileContents' => null !== $fileImport->getFileContents() ? (string) CompressedString::fromUncompressed($fileImport->getFileContents()) : null,
            'source' => $fileImport->getSource()->value,
            'status' => $fileImport->getStatus()->value,
            'errorMessage' => $fileImport->getErrorMessage(),
            'activityId' => $fileImport->getActivityId() instanceof ActivityId ? (string) $fileImport->getActivityId() : null,
            'importedOn' => $fileImport->getImportedOn(),
        ]);
    }

    public function delete(FileImportId $fileImportId): void
    {
        $sql = 'DELETE FROM FileImport WHERE fileImportId = :fileImportId';

        $this->connection->executeStatement($sql, [
            'fileImportId' => $fileImportId,
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM FileImport WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function find(FileImportId $fileImportId): FileImport
    {
        $sql = 'SELECT * FROM FileImport WHERE fileImportId = :fileImportId';

        if (!$result = $this->connection->executeQuery($sql, [
            'fileImportId' => $fileImportId,
        ])->fetchAssociative()) {
            throw new EntityNotFound(sprintf('FileImport "%s" not found', $fileImportId));
        }

        return FileImport::fromState(
            fileImportId: FileImportId::fromString($result['fileImportId']),
            originalFilename: $result['originalFilename'],
            fileContents: null !== $result['fileContents'] ? CompressedString::fromCompressed($result['fileContents'])->uncompress() : null,
            source: ImportSource::from($result['source']),
            status: FileImportStatus::from($result['status']),
            errorMessage: $result['errorMessage'],
            activityId: null !== $result['activityId'] ? ActivityId::fromString($result['activityId']) : null,
            importedOn: SerializableDateTime::fromString($result['importedOn']),
        );
    }
}
