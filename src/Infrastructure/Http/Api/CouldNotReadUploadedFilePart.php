<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use Symfony\Component\HttpFoundation\Response;

final class CouldNotReadUploadedFilePart extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ApiErrorCode $error,
    ) {
        parent::__construct($message);
    }

    public static function notMultipart(): self
    {
        return new self('Send the file as multipart/form-data.', Response::HTTP_UNSUPPORTED_MEDIA_TYPE, ApiErrorCode::UNSUPPORTED_MEDIA_TYPE);
    }

    public static function tooLarge(): self
    {
        return new self('The uploaded file is too large.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE, ApiErrorCode::FILE_TOO_LARGE);
    }

    public static function missing(string $message): self
    {
        return new self($message, Response::HTTP_BAD_REQUEST, ApiErrorCode::MISSING_FILE);
    }

    public static function couldNotBeStored(): self
    {
        return new self('The upload could not be stored.', Response::HTTP_INTERNAL_SERVER_ERROR, ApiErrorCode::INTERNAL_ERROR);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getError(): ApiErrorCode
    {
        return $this->error;
    }
}
