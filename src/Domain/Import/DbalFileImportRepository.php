<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\String\CompressedString;

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

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM FileImport WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }
}
