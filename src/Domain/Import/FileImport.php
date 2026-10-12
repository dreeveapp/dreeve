<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\Eventing\RecordsEvents;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'FileImport_originalFilename', columns: ['originalFilename'])]
final class FileImport
{
    use RecordsEvents;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private readonly FileImportId $fileImportId,
        #[ORM\Column(type: 'string')]
        private readonly string $originalFilename,
        #[ORM\Column(type: 'blob', nullable: true)]
        private readonly ?string $fileContents,
        #[ORM\Column(type: 'string')]
        private readonly ImportSource $source,
        #[ORM\Column(type: 'string')]
        private readonly FileImportStatus $status,
        #[ORM\Column(type: 'text', nullable: true)]
        private readonly ?string $errorMessage,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?ActivityId $activityId,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly SerializableDateTime $importedOn,
    ) {
    }

    public static function create(
        FileImportId $fileImportId,
        string $originalFilename,
        ImportSource $source,
        FileImportStatus $status,
        ?string $errorMessage,
        ?ActivityId $activityId,
        SerializableDateTime $importedOn,
    ): self {
        return new self(
            fileImportId: $fileImportId,
            originalFilename: $originalFilename,
            fileContents: null,
            source: $source,
            status: $status,
            errorMessage: $errorMessage,
            activityId: $activityId,
            importedOn: $importedOn,
        );
    }

    public static function createFromRawFile(
        FileImportId $fileImportId,
        RawActivityFile $file,
        ImportSource $source,
        FileImportStatus $status,
        ?string $errorMessage,
        ?ActivityId $activityId,
        SerializableDateTime $importedOn,
    ): self {
        return new self(
            fileImportId: $fileImportId,
            originalFilename: $file->getPath()->getFilename(),
            fileContents: $file->getContents(),
            source: $source,
            status: $status,
            errorMessage: $errorMessage,
            activityId: $activityId,
            importedOn: $importedOn,
        );
    }

    public static function fromState(
        FileImportId $fileImportId,
        string $originalFilename,
        ?string $fileContents,
        ImportSource $source,
        FileImportStatus $status,
        ?string $errorMessage,
        ?ActivityId $activityId,
        SerializableDateTime $importedOn,
    ): self {
        return new self(
            fileImportId: $fileImportId,
            originalFilename: $originalFilename,
            fileContents: $fileContents,
            source: $source,
            status: $status,
            errorMessage: $errorMessage,
            activityId: $activityId,
            importedOn: $importedOn,
        );
    }

    public function delete(): void
    {
        $this->recordThat(new FileImportWasDeleted(
            fileImportId: $this->fileImportId,
            activityId: $this->activityId
        ));
    }

    public function getId(): FileImportId
    {
        return $this->fileImportId;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getFileContents(): ?string
    {
        return $this->fileContents;
    }

    public function getSource(): ImportSource
    {
        return $this->source;
    }

    public function getStatus(): FileImportStatus
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getActivityId(): ?ActivityId
    {
        return $this->activityId;
    }

    public function getImportedOn(): SerializableDateTime
    {
        return $this->importedOn;
    }
}
