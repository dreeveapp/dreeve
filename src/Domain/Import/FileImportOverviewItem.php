<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Infrastructure\Repository\Item;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class FileImportOverviewItem implements Item
{
    private function __construct(
        private string $originalFilename,
        private ImportSource $source,
        private FileImportStatus $status,
        private SerializableDateTime $importedOn,
        private ?string $errorMessage,
        private ?ActivityId $activityId,
        private ?string $activityName,
    ) {
    }

    public static function fromState(
        string $originalFilename,
        ImportSource $source,
        FileImportStatus $status,
        SerializableDateTime $importedOn,
        ?string $errorMessage,
        ?ActivityId $activityId,
        ?string $activityName,
    ): self {
        return new self(
            originalFilename: $originalFilename,
            source: $source,
            status: $status,
            importedOn: $importedOn,
            errorMessage: $errorMessage,
            activityId: $activityId,
            activityName: $activityName,
        );
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getSource(): ImportSource
    {
        return $this->source;
    }

    public function isFailed(): bool
    {
        return FileImportStatus::FAILED === $this->status;
    }

    public function wasSkipped(): bool
    {
        return FileImportStatus::SKIPPED === $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getActivityId(): ?ActivityId
    {
        return $this->activityId;
    }

    public function getActivityName(): ?string
    {
        return $this->activityName;
    }

    public function getImportedOn(): SerializableDateTime
    {
        return $this->importedOn;
    }
}
