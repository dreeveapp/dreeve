<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity\Media;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Integration\AI\MediaLocation\LocationSuggestion;
use App\Domain\Integration\AI\MediaLocation\LocationSuggestionConfidence;
use App\Domain\Integration\AI\MediaLocation\MediaItemLocationSuggester;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Security\AdminCsrfToken;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\AI\Platform\Exception\ExceptionInterface as AIPlatformException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
final readonly class SuggestActivityMediaItemLocationRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private MediaItemRepository $mediaItemRepository,
        private SettingsRepository $settingsRepository,
        private MediaItemLocationSuggester $mediaItemLocationSuggester,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/admin/activities/{activityId}/media/{mediaItemId}/suggest-location', name: 'admin_suggest_activity_media_item_location', methods: ['POST'], priority: 10)]
    public function handle(string $activityId, string $mediaItemId, Request $request): JsonResponse
    {
        if (!$this->csrfTokenManager->isTokenValid(AdminCsrfToken::fromRequest($request))) {
            return new JsonResponse(['error' => $this->translator->trans('Invalid CSRF token.', [], 'admin')], Response::HTTP_FORBIDDEN);
        }

        if (!$this->settingsRepository->ai()->isAIMediaLocationSuggestionEnabled()) {
            throw new NotFoundHttpException('Photo location suggestions are not enabled');
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
            $takenOn = null;
        }

        try {
            $suggestion = $this->mediaItemLocationSuggester->suggest(
                activity: $activity,
                mediaItem: $this->mediaItemRepository->find($parsedMediaItemId),
                takenOn: $takenOn,
            );
        } catch (AIPlatformException|HttpClientException $e) {
            return new JsonResponse(['error' => $this->translator->trans('Your AI provider could not suggest a location: {error}', ['{error}' => $e->getMessage()], 'admin')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$suggestion instanceof LocationSuggestion) {
            return new JsonResponse(['error' => $this->translator->trans('Your AI provider could not tell where on the route this photo was taken.', [], 'admin')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'fields' => [
                'latitude' => round($suggestion->getCoordinate()->getLatitude()->toFloat(), 6),
                'longitude' => round($suggestion->getCoordinate()->getLongitude()->toFloat(), 6),
            ],
            'message' => $this->translator->trans('{reasoning} (confidence: {confidence})', [
                '{reasoning}' => $suggestion->getReasoning(),
                '{confidence}' => match ($suggestion->getConfidence()) {
                    LocationSuggestionConfidence::LOW => $this->translator->trans('low', [], 'admin'),
                    LocationSuggestionConfidence::MEDIUM => $this->translator->trans('medium', [], 'admin'),
                    LocationSuggestionConfidence::HIGH => $this->translator->trans('high', [], 'admin'),
                },
            ], 'admin'),
        ]);
    }
}
