<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImport;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class FileImportBuilder
{
    private FileImportId $fileImportId;
    private string $originalFilename = 'activity.fit';
    private ?string $fileContents = null;
    private ImportSource $source = ImportSource::FIT_FILE;
    private FileImportStatus $status = FileImportStatus::SUCCESS;
    private ?string $errorMessage = null;
    private ?ActivityId $activityId = null;
    private SerializableDateTime $importedOn;

    private function __construct()
    {
        $this->fileImportId = FileImportId::fromUnprefixed('test');
        $this->importedOn = SerializableDateTime::fromString('2026-06-04 10:00:00');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): FileImport
    {
        return FileImport::fromState(
            fileImportId: $this->fileImportId,
            originalFilename: $this->originalFilename,
            fileContents: $this->fileContents,
            source: $this->source,
            status: $this->status,
            errorMessage: $this->errorMessage,
            activityId: $this->activityId,
            importedOn: $this->importedOn,
        );
    }

    public function withFileImportId(FileImportId $fileImportId): self
    {
        $this->fileImportId = $fileImportId;

        return $this;
    }

    public function withOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function withFileContents(?string $fileContents): self
    {
        $this->fileContents = $fileContents;

        return $this;
    }

    public function withSource(ImportSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function withStatus(FileImportStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function withErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function withActivityId(?ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withImportedOn(SerializableDateTime $importedOn): self
    {
        $this->importedOn = $importedOn;

        return $this;
    }
}
