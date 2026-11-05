<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\FindTripSuggestions;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\WorldType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindTripSuggestionsQueryHandler implements QueryHandler
{
    private const float AWAY_FROM_HOME_IN_METERS = 75000;
    private const int MAX_DAYS_BETWEEN_ACTIVITIES = 4;
    private const int MIN_NUMBER_OF_ACTIVITIES = 2;
    private const int MAX_NUMBER_OF_DAYS = 21;
    private const float MAX_SHARE_OF_A_LOCATION = 0.05;

    public function __construct(
        private Connection $connection,
        private ActivityCollectionRepository $activityCollectionRepository,
    ) {
    }

    /**
     * @param Query<Response> $query
     */
    public function handle(Query $query): Response
    {
        assert($query instanceof FindTripSuggestions);

        /** @var array<int, array{activityId: string, startDateTime: string, latitude: float, longitude: float, routeGeography: ?string}> $rows */
        $rows = $this->connection->executeQuery(
            'SELECT activityId, startDateTime, startingCoordinateLatitude AS latitude, startingCoordinateLongitude AS longitude, routeGeography
             FROM Activity
             WHERE worldType = :worldType AND startingCoordinateLatitude IS NOT NULL AND startingCoordinateLongitude IS NOT NULL
             ORDER BY startDateTime ASC',
            ['worldType' => WorldType::REAL_WORLD->value],
        )->fetchAllAssociative();

        if ([] === $rows) {
            return new FindTripSuggestionsResponse([]);
        }

        $locations = array_map(
            static fn (array $row): string => sprintf('%.1f,%.1f', round($row['latitude'], 1), round($row['longitude'], 1)),
            $rows,
        );
        $numberOfActivitiesPerLocation = array_count_values($locations);
        arsort($numberOfActivitiesPerLocation);
        [$homeLatitude, $homeLongitude] = array_map(floatval(...), explode(',', (string) array_key_first($numberOfActivitiesPerLocation)));

        $groups = [];
        $currentGroup = [];
        foreach ($rows as $index => $row) {
            $isAway = GeoMath::haversineDistance($homeLatitude, $homeLongitude, $row['latitude'], $row['longitude']) > self::AWAY_FROM_HOME_IN_METERS
                && $numberOfActivitiesPerLocation[$locations[$index]] / count($rows) < self::MAX_SHARE_OF_A_LOCATION;
            $day = SerializableDateTime::fromString($row['startDateTime'])->setTime(0, 0);

            if (!$isAway) {
                $groups[] = $currentGroup;
                $currentGroup = [];
                continue;
            }

            $previousAwayActivity = end($currentGroup);
            if ($previousAwayActivity && $previousAwayActivity['day']->diff($day)->days > self::MAX_DAYS_BETWEEN_ACTIVITIES) {
                $groups[] = $currentGroup;
                $currentGroup = [];
            }
            $currentGroup[] = [...$row, 'day' => $day];
        }
        $groups[] = $currentGroup;

        $activityIdsInACollection = array_flip(array_map(
            strval(...),
            $this->activityCollectionRepository->findAll()->getActivityIds()->toArray(),
        ));

        $tripSuggestions = [];
        foreach ($groups as $group) {
            if (count($group) < self::MIN_NUMBER_OF_ACTIVITIES) {
                continue;
            }

            $startDate = $group[0]['day'];
            $endDate = $group[array_key_last($group)]['day'];
            if ($startDate->diff($endDate)->days + 1 > self::MAX_NUMBER_OF_DAYS) {
                continue;
            }

            if (array_any($group, static fn (array $activity): bool => isset($activityIdsInACollection[$activity['activityId']]))) {
                continue;
            }

            $routeGeographies = array_map(
                static fn (array $activity): RouteGeography => RouteGeography::create(Json::decode($activity['routeGeography'] ?: '{}')),
                $group,
            );
            $numberOfActivitiesPerCountryCode = array_count_values(array_merge(...array_map(
                static fn (RouteGeography $routeGeography): array => $routeGeography->getPassedThroughCountries(),
                $routeGeographies,
            )));
            arsort($numberOfActivitiesPerCountryCode);

            $tripSuggestions[] = TripSuggestion::create(
                activityIds: ActivityIds::fromArray(array_map(
                    static fn (array $activity): ActivityId => ActivityId::fromString($activity['activityId']),
                    $group,
                )),
                startDate: $startDate,
                endDate: $endDate,
                countryCodes: array_map(strval(...), array_keys($numberOfActivitiesPerCountryCode)),
                places: array_values(array_unique(array_filter(array_map(
                    static fn (RouteGeography $routeGeography): ?string => $routeGeography->getStartingPointCity(),
                    $routeGeographies,
                )))),
            );
        }

        return new FindTripSuggestionsResponse(array_reverse($tripSuggestions));
    }
}
