<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use Symfony\Component\HttpFoundation\Response;

final class CouldNotReadUploadedFilePart extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $error,
    ) {
        parent::__construct($message);
    }

    public static function notMultipart(): self
    {
        return new self('Send the file as multipart/form-data.', Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'unsupported_media_type');
    }

    public static function tooLarge(): self
    {
        return new self('The uploaded file is too large.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'file_too_large');
    }

    public static function missing(string $message): self
    {
        return new self($message, Response::HTTP_BAD_REQUEST, 'missing_file');
    }

    public static function couldNotBeStored(): self
    {
        return new self('The upload could not be stored.', Response::HTTP_INTERNAL_SERVER_ERROR, 'internal_error');
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getError(): string
    {
        return $this->error;
    }
}
