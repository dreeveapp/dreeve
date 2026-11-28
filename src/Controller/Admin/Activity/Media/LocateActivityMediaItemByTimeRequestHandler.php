<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity\Media;

use App\Controller\Admin\DispatchCommandRequestHandler;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\ActivityRouteLocator;
use App\Domain\Media\MediaItemId;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
final readonly class LocateActivityMediaItemByTimeRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityRouteLocator $activityRouteLocator,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/admin/activities/{activityId}/media/{mediaItemId}/locate-by-time', name: 'admin_locate_activity_media_item_by_time', methods: ['POST'], priority: 10)]
    public function handle(string $activityId, string $mediaItemId, Request $request): JsonResponse
    {
        $token = new CsrfToken(DispatchCommandRequestHandler::CSRF_TOKEN_ID, $request->headers->get(DispatchCommandRequestHandler::CSRF_TOKEN_HEADER, ''));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            return new JsonResponse(['error' => $this->translator->trans('Invalid CSRF token.', [], 'admin')], Response::HTTP_FORBIDDEN);
        }

        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
            $parsedMediaItemId = MediaItemId::fromString($mediaItemId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Media item "%s" of activity "%s" not found', $mediaItemId, $activityId));
        }

        if (!$activity->getMediaItemIds()->has($parsedMediaItemId)) {
            throw new NotFoundHttpException(sprintf('Media item "%s" does not belong to activity "%s"', $mediaItemId, $activityId));
        }

        try {
            $takenOn = SerializableDateTime::fromLocalDateTimeString($request->request->getString('takenOn'));
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => $this->translator->trans('Fill in “Taken on” first.', [], 'admin')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $coordinate = $this->activityRouteLocator->locateAtDateTime(
            activity: $activity,
            on: $takenOn,
        );
        if (!$coordinate instanceof Coordinate) {
            return new JsonResponse(['error' => $this->translator->trans('The “Taken on” time falls outside this activity.', [], 'admin')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'fields' => [
                'latitude' => round($coordinate->getLatitude()->toFloat(), 6),
                'longitude' => round($coordinate->getLongitude()->toFloat(), 6),
            ],
        ]);
    }
}
