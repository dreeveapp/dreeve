<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\AppVersion;
use App\Domain\Import\ImportMode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class StatusRequestHandler
{
    public function __construct(
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/api/v1/status', name: 'api_v1_status', methods: ['GET'], priority: 3)]
    public function handle(): JsonResponse
    {
        return new JsonResponse([
            'app' => 'dreeve',
            'version' => AppVersion::getSemanticVersion(),
            'canUpload' => $this->importMode->isFiles(),
        ]);
    }
}
