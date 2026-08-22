<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Domain\Import\InvalidActivityFileName;
use App\Domain\Import\UploadActivityFile\CannotUploadActivityFile;
use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Http\Api\UploadLimits;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityUploadRequestHandler
{
    private const string PART_NAME = 'file';

    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    #[Route(path: '/api/v1/activity/upload', name: 'api_v1_activity_upload', methods: ['POST', 'PUT'], priority: 3)]
    public function handle(Request $request): Response
    {
        $file = $request->files->get(self::PART_NAME);
        if (!$file instanceof UploadedFile) {
            $contentType = (string) $request->headers->get('Content-Type');
            if ('' !== $contentType && !str_starts_with($contentType, 'multipart/form-data')) {
                return new ApiErrorResponse(
                    statusCode: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                    error: 'unsupported_media_type',
                    message: 'Send the file as multipart/form-data.',
                );
            }

            if ((int) $request->headers->get('Content-Length', '0') > UploadLimits::fromIni()->getMaxPostSizeInBytes()) {
                return new ApiErrorResponse(
                    statusCode: Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                    error: 'file_too_large',
                    message: 'The uploaded file is too large.',
                );
            }

            return new ApiErrorResponse(
                statusCode: Response::HTTP_BAD_REQUEST,
                error: 'missing_file',
                message: sprintf('A "%s" part is required.', self::PART_NAME),
            );
        }

        if (UPLOAD_ERR_OK !== $file->getError()) {
            return match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => new ApiErrorResponse(
                    statusCode: Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                    error: 'file_too_large',
                    message: 'The uploaded file is too large.',
                ),
                UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE => new ApiErrorResponse(
                    statusCode: Response::HTTP_BAD_REQUEST,
                    error: 'missing_file',
                    message: 'The file was not uploaded completely.',
                ),
                default => new ApiErrorResponse(
                    statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                    error: 'internal_error',
                    message: 'The upload could not be stored.',
                ),
            };
        }

        $contents = (string) file_get_contents($file->getPathname());
        if ('' === $contents) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_BAD_REQUEST,
                error: 'missing_file',
                message: 'The uploaded file is empty.',
            );
        }

        try {
            $command = UploadActivityFile::fromFile($file->getClientOriginalName(), $contents);
        } catch (InvalidActivityFileName $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                error: 'unsupported_file_type',
                message: $e->getMessage(),
            );
        }

        try {
            $this->commandBus->dispatch($command);
        } catch (CannotUploadActivityFile $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_CONFLICT,
                error: 'import_mode_not_files',
                message: $e->getMessage(),
            );
        }

        return new JsonResponse([
            'status' => 'queued',
            'message' => 'File queued for import. It will be processed within five minutes.',
        ], Response::HTTP_ACCEPTED);
    }
}
