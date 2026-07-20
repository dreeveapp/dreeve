<?php

declare(strict_types=1);

namespace App\Controller\Admin\File;

use App\Domain\Import\DeleteFileImport\DeleteFileImport;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportOverviewRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageFileImportFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private FileImportOverviewRepository $fileImportOverviewRepository,
    ) {
    }

    #[Route(path: '/admin/file-imports/{fileImportId}/delete', name: 'admin_delete_file_import', methods: ['GET'], priority: 10)]
    public function handleDelete(string $fileImportId): Response
    {
        return new Response($this->twig->render('html/admin/page/file/delete-file-import.html.twig', [
            'dispatchCommand' => DeleteFileImport::getCommandName(),
            'fileImport' => $this->fileImportOverviewRepository->findOneByFileImportId(
                FileImportId::fromString($fileImportId)
            ),
        ]));
    }
}
