<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\AddActivityImage\AddActivityImage;
use App\Domain\Image\ImageDirectory;
use App\Domain\Image\ImageExtension;
use App\Domain\Image\ImagePath;
use App\Domain\Import\ImportMode;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Http\Api\CouldNotReadUploadedFilePart;
use App\Infrastructure\Http\Api\UploadedFilePart;
use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityImageAddRequestHandler
{
    private const string PART_NAME = 'file';

    public function __construct(
        private ActivityRepository $activityRepository,
        private CommandBus $commandBus,
        private ImportMode $importMode,
        private UuidFactory $uuidFactory,
        private AppUrl $appUrl,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}/images', name: 'api_v1_activity_image_add', methods: ['POST'], priority: 3)]
    public function handle(string $activityId, Request $request): Response
    {
        if (!$this->importMode->isFiles()) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_CONFLICT,
                error: 'import_mode_not_files',
                message: 'Images can only be added to activities when running in file import mode.',
            );
        }

        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        try {
            $filePart = UploadedFilePart::fromRequest($request, self::PART_NAME);
        } catch (CouldNotReadUploadedFilePart $e) {
            return new ApiErrorResponse(
                statusCode: $e->getStatusCode(),
                error: $e->getError(),
                message: $e->getMessage(),
            );
        }

        $extension = match (getimagesizefromstring($filePart->getContents())[2] ?? null) {
            IMAGETYPE_JPEG => ImageExtension::JPG,
            IMAGETYPE_PNG => ImageExtension::PNG,
            IMAGETYPE_WEBP => ImageExtension::WEBP,
            default => null,
        };
        if (null === $extension) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                error: 'unsupported_file_type',
                message: 'The file is not a jpg, png or webp image.',
            );
        }

        $path = ImagePath::fromFileSystemPath(sprintf(
            '%s/%s.%s',
            ImageDirectory::ACTIVITIES->value,
            $this->uuidFactory->random(),
            $extension->value,
        ));
        $this->commandBus->dispatch(new AddActivityImage(
            activityId: $activity->getId(),
            path: $path,
            content: $filePart->getContents(),
        ));

        return ActivityImageResponse::created($path, $this->appUrl);
    }
}
