<?php

declare(strict_types=1);

namespace App\Controller\Admin\File;

use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\AttachmentResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class DownloadFileImportRequestHandler
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
    ) {
    }

    #[Route(path: '/admin/file-imports/{fileImportId}/download', name: 'admin_download_file_import', methods: ['GET'], priority: 10)]
    public function handle(string $fileImportId): AttachmentResponse
    {
        try {
            $fileImport = $this->fileImportRepository->find(FileImportId::fromString($fileImportId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('File import "%s" not found', $fileImportId));
        }

        if (null === $fileContents = $fileImport->getFileContents()) {
            throw new NotFoundHttpException(sprintf('File import "%s" has no stored file contents', $fileImportId));
        }

        return new AttachmentResponse($fileContents, $fileImport->getOriginalFilename());
    }
}
