<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity\Collection;

use App\Application\Countries;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\AddActivityCollection\AddActivityCollection;
use App\Domain\Activity\Collection\DismissTripSuggestion\DismissTripSuggestion;
use App\Domain\Activity\Collection\FindTripSuggestions\FindTripSuggestions;
use App\Domain\Activity\Collection\FindTripSuggestions\TripSuggestion;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Response\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class TripSuggestionsRequestHandler
{
    public function __construct(
        private Environment $twig,
        private QueryBus $queryBus,
        private Countries $countries,
        private ActivityRepository $activityRepository,
    ) {
    }

    #[Route(path: '/admin/collections/suggestions', name: 'admin_activity_collection_trip_suggestions', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        $tripSuggestions = $this->queryBus->ask(new FindTripSuggestions())->getTripSuggestions();
        $activities = $this->activityRepository->findByIds($tripSuggestions->getActivityIds());

        return new HtmlResponse($this->twig->render('html/admin/page/activity-collection/trip-suggestions.html.twig', [
            'addActivityCollectionCommand' => AddActivityCollection::getCommandName(),
            'tripType' => ActivityCollectionType::TRIP,
            'tripSuggestions' => $tripSuggestions->map(
                fn (TripSuggestion $tripSuggestion): array => [
                    'tripSuggestion' => $tripSuggestion,
                    'activityCollectionId' => ActivityCollectionId::random(),
                    'activities' => $activities->filter(
                        static fn (Activity $activity): bool => $tripSuggestion->getActivityIds()->has($activity->getId()),
                    ),
                    'name' => implode(', ', array_filter([
                        $tripSuggestion->getCountryCodes() ? $this->countries->getName($tripSuggestion->getCountryCodes()[0]) : null,
                        $tripSuggestion->getStartDate()->translatedFormat('M Y'),
                    ])),
                ],
            ),
        ]));
    }

    #[Route(path: '/admin/collections/suggestions/{tripSuggestionId}/dismiss', name: 'admin_dismiss_trip_suggestion', methods: ['GET'], priority: 10)]
    public function handleDismiss(string $tripSuggestionId): HtmlResponse
    {
        $tripSuggestion = $this->queryBus->ask(new FindTripSuggestions())->getTripSuggestions()->find(
            static fn (TripSuggestion $tripSuggestion): bool => $tripSuggestionId === (string) $tripSuggestion->getId(),
        ) ?? throw new EntityNotFound(sprintf('TripSuggestion "%s" not found', $tripSuggestionId));

        return new HtmlResponse($this->twig->render('html/admin/page/activity-collection/dismiss-trip-suggestion.html.twig', [
            'dispatchCommand' => DismissTripSuggestion::getCommandName(),
            'tripSuggestion' => $tripSuggestion,
        ]));
    }
}
