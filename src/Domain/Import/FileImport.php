<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'UNIQ_FileImport_fileHash', columns: ['fileHash'])]
final readonly class FileImport
{
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private FileImportId $fileImportId,
        #[ORM\Column(type: 'string')]
        private string $originalFilename,
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $fileHash,
        #[ORM\Column(type: 'blob', nullable: true)]
        private ?string $fileContents,
        #[ORM\Column(type: 'string')]
        private ImportSource $source,
        #[ORM\Column(type: 'string')]
        private FileImportStatus $status,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $errorMessage,
        #[ORM\Column(type: 'string', nullable: true)]
        private ?ActivityId $activityId,
        #[ORM\Column(type: 'datetime_immutable')]
        private SerializableDateTime $importedOn,
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
            fileHash: null,
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
            fileHash: $file->getHash(),
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
        ?string $fileHash,
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
            fileHash: $fileHash,
            fileContents: $fileContents,
            source: $source,
            status: $status,
            errorMessage: $errorMessage,
            activityId: $activityId,
            importedOn: $importedOn,
        );
    }

    public function getId(): FileImportId
    {
        return $this->fileImportId;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
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
