<?php

declare(strict_types=1);

namespace App\Controller\Admin\File;

use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportOverviewRepository;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageFileImportOverviewRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private Environment $twig,
        private FileImportOverviewRepository $fileImportOverviewRepository,
    ) {
    }

    #[Route(path: '/admin/file-imports', name: 'admin_file_imports', methods: ['GET'], priority: 10)]
    public function index(Request $request): Response
    {
        $filters = FileImportOverviewFilters::fromRequest($request);

        return new Response($this->twig->render('html/admin/page/file/file-imports.html.twig', [
            'overview' => $this->fileImportOverviewRepository->find(
                $this->paginationFromRequest($request),
                $filters,
            ),
            'filters' => $filters,
            'statusOptions' => FileImportStatus::cases(),
            'sourceOptions' => ImportSource::fileBasedSources(),
        ]));
    }
}
