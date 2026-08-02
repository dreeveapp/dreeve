<?php

declare(strict_types=1);

namespace App\Domain\Import\UploadActivityFile;

use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;
use App\Infrastructure\CQRS\Command\SuppressesFlashMessage;
use App\Infrastructure\ValueObject\String\Path;

#[RequiresRebuild]
#[SuppressesFlashMessage]
final readonly class UploadActivityFile extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private string $filename,
        private string $contents,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['filename'], $payload['content'])
            || !is_string($payload['filename'])
            || !is_string($payload['content'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "filename" and "content" are required.');
        }

        $filename = basename($payload['filename']);

        if (!SupportedFileExtension::tryFrom(Path::fromString($filename)->getExtension())) {
            throw CouldNotDeserializeCommand::invalidPayload('The file type is not supported.');
        }

        $contents = base64_decode($payload['content'], strict: true);
        if (false === $contents || '' === $contents) {
            throw CouldNotDeserializeCommand::invalidPayload('The file content must be valid, non-empty base64.');
        }

        return new self(
            filename: $filename,
            contents: $contents,
        );
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getContents(): string
    {
        return $this->contents;
    }
}
