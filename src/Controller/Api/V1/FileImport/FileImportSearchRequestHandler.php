<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\FileImport;

use App\Domain\Import\Overview\FileImportOverviewItem;
use App\Domain\Import\Search\FileImportSearchFilters;
use App\Domain\Import\Search\FileImportSearchRepository;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class FileImportSearchRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private FileImportSearchRepository $fileImportSearchRepository,
    ) {
    }

    #[Route(path: '/api/v1/file-imports', name: 'api_v1_file_imports', methods: ['GET'], priority: 3)]
    public function handle(Request $request): JsonResponse
    {
        $pagination = $this->paginationFromRequest($request);
        $overview = $this->fileImportSearchRepository->find($pagination, FileImportSearchFilters::fromRequest($request));

        return new JsonResponse([
            'fileImports' => array_map(
                static fn (FileImportOverviewItem $fileImport): array => [
                    'id' => (string) $fileImport->getFileImportId() ?: null,
                    'filename' => $fileImport->getOriginalFilename(),
                    'source' => $fileImport->getSource()->value,
                    'status' => $fileImport->getStatus()->value,
                    'errorMessage' => $fileImport->getErrorMessage(),
                    'activityId' => (string) $fileImport->getActivityId() ?: null,
                    'importedOn' => $fileImport->getImportedOn()?->format('Y-m-d\TH:i:s'),
                ],
                $overview->getItems()
            ),
            'pagination' => [
                'page' => $pagination->getCurrentPage(),
                'size' => $pagination->getLimit(),
                'total' => $overview->getTotal(),
                'totalPages' => (int) ceil($overview->getTotal() / $pagination->getLimit()),
            ],
        ]);
    }
}
