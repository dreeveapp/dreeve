<?php

declare(strict_types=1);

namespace App\Controller\File;

use App\Domain\Activity\ActivityId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Http\AttachmentResponse;
use App\Infrastructure\Security\Visitor;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityOriginalFileRequestHandler
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
        private Visitor $visitor,
    ) {
    }

    #[Route(path: '/activities/{activityId}/original-file', name: 'activity_original_file', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): AttachmentResponse
    {
        if (!$this->visitor->canDownloadOriginalFiles()) {
            throw new NotFoundHttpException('Not found');
        }

        $fileImport = $this->fileImportRepository->findForActivity(ActivityId::fromString($activityId));

        return new AttachmentResponse((string) $fileImport->getFileContents(), $fileImport->getOriginalFilename());
    }
}
