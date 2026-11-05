<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity\Collection;

use App\Application\Countries;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\AddActivityCollection\AddActivityCollection;
use App\Domain\Activity\Collection\FindTripSuggestions\FindTripSuggestions;
use App\Domain\Activity\Collection\FindTripSuggestions\TripSuggestion;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
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
    ) {
    }

    #[Route(path: '/admin/collections/suggestions', name: 'admin_activity_collection_trip_suggestions', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/activity-collection/trip-suggestions.html.twig', [
            'addActivityCollectionCommand' => AddActivityCollection::getCommandName(),
            'tripType' => ActivityCollectionType::TRIP,
            'tripSuggestions' => array_map(
                fn (TripSuggestion $tripSuggestion): array => [
                    'tripSuggestion' => $tripSuggestion,
                    'activityCollectionId' => ActivityCollectionId::random(),
                    'name' => implode(', ', array_filter([
                        $tripSuggestion->getCountryCodes() ? $this->countries->getName($tripSuggestion->getCountryCodes()[0]) : null,
                        $tripSuggestion->getStartDate()->translatedFormat('M Y'),
                    ])),
                ],
                $this->queryBus->ask(new FindTripSuggestions())->getTripSuggestions(),
            ),
        ]));
    }
}
