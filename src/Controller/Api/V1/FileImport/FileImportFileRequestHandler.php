<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\FileImport;

use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Api\ApiErrorCode;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Http\AttachmentResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class FileImportFileRequestHandler
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
    ) {
    }

    #[Route(path: '/api/v1/file-imports/{fileImportId}/file', name: 'api_v1_file_import_file', methods: ['GET'], priority: 3)]
    public function handle(string $fileImportId): Response
    {
        try {
            $fileImport = $this->fileImportRepository->find(FileImportId::fromString($fileImportId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('File import "%s" not found', $fileImportId));
        }

        if (null === $fileContents = $fileImport->getFileContents()) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                error: ApiErrorCode::FILE_NOT_AVAILABLE,
                message: sprintf('File import "%s" has no stored file.', $fileImportId),
            );
        }

        return new AttachmentResponse(
            contents: $fileContents,
            filename: $fileImport->getOriginalFilename(),
        );
    }
}
