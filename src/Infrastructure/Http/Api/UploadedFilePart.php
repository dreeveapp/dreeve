<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final readonly class UploadedFilePart
{
    private function __construct(
        private string $clientOriginalName,
        private string $contents,
    ) {
    }

    public static function fromRequest(Request $request, string $partName): self
    {
        $file = $request->files->get($partName);
        if (!$file instanceof UploadedFile) {
            $contentType = (string) $request->headers->get('Content-Type');
            if ('' !== $contentType && !str_starts_with($contentType, 'multipart/form-data')) {
                throw CouldNotReadUploadedFilePart::notMultipart();
            }

            if ((int) $request->headers->get('Content-Length', '0') > UploadLimits::fromIni()->getMaxPostSizeInBytes()) {
                throw CouldNotReadUploadedFilePart::tooLarge();
            }

            throw CouldNotReadUploadedFilePart::missing(sprintf('A "%s" part is required.', $partName));
        }

        if (UPLOAD_ERR_OK !== $file->getError()) {
            throw match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => CouldNotReadUploadedFilePart::tooLarge(),
                UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE => CouldNotReadUploadedFilePart::missing('The file was not uploaded completely.'),
                default => CouldNotReadUploadedFilePart::couldNotBeStored(),
            };
        }

        $contents = (string) file_get_contents($file->getPathname());
        if ('' === $contents) {
            throw CouldNotReadUploadedFilePart::missing('The uploaded file is empty.');
        }

        return new self(
            clientOriginalName: $file->getClientOriginalName(),
            contents: $contents,
        );
    }

    public function getClientOriginalName(): string
    {
        return $this->clientOriginalName;
    }

    public function getContents(): string
    {
        return $this->contents;
    }
}
