<?php

namespace App\Tests\Domain\Activity\Collection\FindTripSuggestions;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\FindTripSuggestions\FindTripSuggestions;
use App\Domain\Activity\Collection\FindTripSuggestions\FindTripSuggestionsQueryHandler;
use App\Domain\Activity\Collection\FindTripSuggestions\FindTripSuggestionsResponse;
use App\Domain\Activity\Collection\FindTripSuggestions\TripSuggestion;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\WorldType;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;

class FindTripSuggestionsQueryHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $activities = [];
        for ($day = 1; $day <= 60; ++$day) {
            $activities[] = ['home-'.$day, SerializableDateTime::fromString('2024-01-01')->modify(sprintf('+%d days', $day - 1))->format('Y-m-d'), '51.20', '3.20', WorldType::REAL_WORLD];
        }
        $activities = [
            ...$activities,
            ['former-home-1', '2024-03-01', '51.20', '4.40', WorldType::REAL_WORLD],
            ['former-home-2', '2024-03-02', '51.20', '4.40', WorldType::REAL_WORLD],
            ['former-home-3', '2024-03-03', '51.20', '4.40', WorldType::REAL_WORLD],
            ['former-home-4', '2024-03-04', '51.20', '4.40', WorldType::REAL_WORLD],
            ['former-home-5', '2024-03-05', '51.20', '4.40', WorldType::REAL_WORLD],
            ['trip-1', '2024-04-10', '39.90', '3.00', WorldType::REAL_WORLD],
            ['zwift-during-trip', '2024-04-12', '-11.64', '166.97', WorldType::ZWIFT],
            ['trip-2', '2024-04-14', '39.90', '3.00', WorldType::REAL_WORLD],
            ['trip-3', '2024-04-15', '39.90', '3.00', WorldType::REAL_WORLD],
            ['day-trip', '2024-05-01', '50.30', '5.90', WorldType::REAL_WORLD],
            ['split-1', '2024-06-01', '45.10', '6.10', WorldType::REAL_WORLD],
            ['home-ride-in-between', '2024-06-02', '51.20', '3.20', WorldType::REAL_WORLD],
            ['split-2', '2024-06-03', '45.10', '6.10', WorldType::REAL_WORLD],
            ['too-long-1', '2024-07-01', '40.40', '-3.70', WorldType::REAL_WORLD],
            ['too-long-2', '2024-07-05', '40.40', '-3.50', WorldType::REAL_WORLD],
            ['too-long-3', '2024-07-09', '40.40', '-3.30', WorldType::REAL_WORLD],
            ['too-long-4', '2024-07-13', '40.40', '-3.10', WorldType::REAL_WORLD],
            ['too-long-5', '2024-07-17', '40.40', '-2.90', WorldType::REAL_WORLD],
            ['too-long-6', '2024-07-21', '40.40', '-2.70', WorldType::REAL_WORLD],
            ['too-long-7', '2024-07-25', '40.40', '-2.50', WorldType::REAL_WORLD],
            ['collected-1', '2024-08-10', '45.60', '10.60', WorldType::REAL_WORLD],
            ['collected-2', '2024-08-11', '45.60', '10.60', WorldType::REAL_WORLD],
        ];

        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        foreach ($activities as [$activityId, $startDate, $latitude, $longitude, $worldType]) {
            $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDate.' 10:00:00'))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    Latitude::fromString($latitude),
                    Longitude::fromString($longitude),
                ))
                ->withWorldType($worldType)
                ->withRouteGeography(RouteGeography::create(match (true) {
                    'trip-1' === $activityId => ['country_code' => 'FR', 'city' => 'Perpignan'],
                    str_starts_with($activityId, 'trip-') => ['country_code' => 'ES', 'city' => 'Pollença'],
                    default => [],
                }))
                ->build(), []));
        }
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('collected-2')]))
            ->build());

        $response = new FindTripSuggestionsQueryHandler(
            connection: $this->getConnection(),
            activityCollectionRepository: $activityCollectionRepository,
        )->handle(new FindTripSuggestions());
        assert($response instanceof FindTripSuggestionsResponse);

        $this->assertEquals(
            [
                TripSuggestion::create(
                    activityIds: ActivityIds::fromArray([
                        ActivityId::fromUnprefixed('trip-1'),
                        ActivityId::fromUnprefixed('trip-2'),
                        ActivityId::fromUnprefixed('trip-3'),
                    ]),
                    startDate: SerializableDateTime::fromString('2024-04-10 00:00:00'),
                    endDate: SerializableDateTime::fromString('2024-04-15 00:00:00'),
                    countryCodes: ['es', 'fr'],
                    places: ['Perpignan', 'Pollença'],
                ),
            ],
            $response->getTripSuggestions(),
        );
    }
}
