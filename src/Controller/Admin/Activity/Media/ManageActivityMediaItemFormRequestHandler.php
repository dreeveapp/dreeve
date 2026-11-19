<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity\Media;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\UpdateMediaItem\UpdateMediaItem;
use App\Infrastructure\Http\Response\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageActivityMediaItemFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private ActivityRepository $activityRepository,
        private MediaItemRepository $mediaItemRepository,
    ) {
    }

    #[Route(path: '/admin/activities/{activityId}/media/{mediaItemId}/edit', name: 'admin_edit_activity_media_item', methods: ['GET'], priority: 10)]
    public function handle(string $activityId, string $mediaItemId): HtmlResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
            $parsedMediaItemId = MediaItemId::fromString($mediaItemId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Media item "%s" of activity "%s" not found', $mediaItemId, $activityId));
        }

        if (!$activity->getMediaItemIds()->has($parsedMediaItemId)) {
            throw new NotFoundHttpException(sprintf('Media item "%s" does not belong to activity "%s"', $mediaItemId, $activityId));
        }

        $mediaItem = $this->mediaItemRepository->find($parsedMediaItemId);

        return new HtmlResponse($this->twig->render('html/admin/page/activity/media/edit-activity-media-item.html.twig', [
            'dispatchCommand' => UpdateMediaItem::getCommandName(),
            'activity' => $activity,
            'mediaItem' => $mediaItem,
        ]));
    }
}
