<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\String\Path;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityDetailRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private MediaItemRepository $mediaItemRepository,
        private AppUrl $appUrl,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}', name: 'api_v1_activity', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): ActivityResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        return ActivityResponse::detail(
            activity: $activity,
            hasGpx: $this->activityStreamRepository->hasOneForActivityAndStreamType($activity->getId(), StreamType::LAT_LNG),
            mediaItems: $this->mediaItemRepository->findByIds(MediaItemIds::fromArray(array_map(
                static fn (string $localImagePath): MediaItemId => MediaItemId::fromUnprefixed(Path::fromString($localImagePath)->getFilenameWithoutExtension()),
                $activity->getLocalImagePaths(),
            ))),
            appUrl: $this->appUrl,
        );
    }
}
