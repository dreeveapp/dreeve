<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\ImportStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ImportStatusRequestHandler
{
    public function __construct(
        private ImportStatus $importStatus,
    ) {
    }

    #[Route(path: '/admin/importStatus', name: 'admin_import_status', methods: ['GET'], priority: 10)]
    public function handle(): JsonResponse
    {
        return new JsonResponse(['pending' => $this->importStatus->isPending()]);
    }
}
