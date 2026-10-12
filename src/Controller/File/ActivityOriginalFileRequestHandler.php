<?php

declare(strict_types=1);

namespace App\Controller\File;

use App\Domain\Activity\ActivityId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\AttachmentResponse;
use App\Infrastructure\Security\AuthenticatedVisitor;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityOriginalFileRequestHandler
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
        private SettingsRepository $settingsRepository,
        private AuthenticatedVisitor $authenticatedVisitor,
    ) {
    }

    #[Route(path: '/activities/{activityId}/original-file', name: 'activity_original_file', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): AttachmentResponse
    {
        if (!$this->authenticatedVisitor->isAuthenticated() && !$this->settingsRepository->security()->visitorsCanDownloadOriginalFiles()) {
            throw new NotFoundHttpException('Not found');
        }

        try {
            $fileImport = $this->fileImportRepository->findForActivity(ActivityId::fromString($activityId));
        } catch (EntityNotFound) {
            throw new NotFoundHttpException(sprintf('Activity "%s" has no original file', $activityId));
        }

        return new AttachmentResponse((string) $fileImport->getFileContents(), $fileImport->getOriginalFilename());
    }
}
