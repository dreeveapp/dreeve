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
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
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
            $activities[] = ['home-'.$day, SerializableDateTime::fromString('2024-01-01')->modify(sprintf('+%d days', $day - 1))->format('Y-m-d'), '51.20', '3.20', WorldType::REAL_WORLD, SportType::RIDE, null];
        }
        $activities = [
            ...$activities,
            ['former-home-1', '2024-03-01', '51.20', '4.40', WorldType::REAL_WORLD, SportType::RIDE, (string) EncodedPolyline::fromCoordinates([[51.20, 4.40], [51.10, 4.60], [51.20, 4.41]])],
            ['former-home-2', '2024-03-02', '51.20', '4.40', WorldType::REAL_WORLD, SportType::RIDE, (string) EncodedPolyline::fromCoordinates([[51.20, 4.40], [51.10, 4.60], [51.20, 4.41]])],
            ['former-home-3', '2024-03-03', '51.20', '4.40', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['former-home-4', '2024-03-04', '51.20', '4.40', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['former-home-5', '2024-03-05', '51.20', '4.40', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['trip-1', '2024-04-10', '39.90', '3.00', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['zwift-during-trip', '2024-04-12', '-11.64', '166.97', WorldType::ZWIFT, SportType::RIDE, null],
            ['trip-2', '2024-04-14', '39.90', '3.00', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['trip-3', '2024-04-15', '39.90', '3.00', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['virtual-1', '2024-04-17', '39.90', '3.00', WorldType::REAL_WORLD, SportType::VIRTUAL_RIDE, null],
            ['virtual-2', '2024-04-18', '39.90', '3.00', WorldType::REAL_WORLD, SportType::VIRTUAL_RIDE, null],
            ['day-trip', '2024-05-01', '50.30', '5.90', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['split-1', '2024-06-01', '45.10', '6.10', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['home-ride-in-between', '2024-06-02', '51.20', '3.20', WorldType::REAL_WORLD, SportType::RIDE, (string) EncodedPolyline::fromCoordinates([[51.20, 3.20], [51.30, 3.40], [51.20, 3.21]])],
            ['split-2', '2024-06-03', '45.10', '6.10', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-1', '2024-07-01', '40.40', '-3.70', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-2', '2024-07-05', '40.40', '-3.50', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-3', '2024-07-09', '40.40', '-3.30', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-4', '2024-07-13', '40.40', '-3.10', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-5', '2024-07-17', '40.40', '-2.90', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-6', '2024-07-21', '40.40', '-2.70', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-7', '2024-07-25', '40.40', '-2.50', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-8', '2024-07-29', '40.40', '-2.30', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['long-trip-9', '2024-08-02', '40.40', '-2.10', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['collected-1', '2024-08-10', '45.60', '10.60', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['collected-2', '2024-08-11', '45.60', '10.60', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['departure', '2024-09-01', '51.20', '3.20', WorldType::REAL_WORLD, SportType::RIDE, (string) EncodedPolyline::fromCoordinates([[51.20, 3.20], [50.50, 4.50], [49.90, 5.60]])],
            ['tour-1', '2024-09-02', '49.90', '5.60', WorldType::REAL_WORLD, SportType::RIDE, null],
            ['tour-2', '2024-09-03', '49.40', '6.20', WorldType::REAL_WORLD, SportType::RIDE, null],
        ];

        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        foreach ($activities as [$activityId, $startDate, $latitude, $longitude, $worldType, $sportType, $polyline]) {
            $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDate.' 10:00:00'))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    Latitude::fromString($latitude),
                    Longitude::fromString($longitude),
                ))
                ->withWorldType($worldType)
                ->withSportType($sportType)
                ->withPolyline($polyline)
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
                        ActivityId::fromUnprefixed('departure'),
                        ActivityId::fromUnprefixed('tour-1'),
                        ActivityId::fromUnprefixed('tour-2'),
                    ]),
                    startDate: SerializableDateTime::fromString('2024-09-01 00:00:00'),
                    endDate: SerializableDateTime::fromString('2024-09-03 00:00:00'),
                    countryCodes: [],
                    places: [],
                ),
                TripSuggestion::create(
                    activityIds: ActivityIds::fromArray([
                        ActivityId::fromUnprefixed('long-trip-1'),
                        ActivityId::fromUnprefixed('long-trip-2'),
                        ActivityId::fromUnprefixed('long-trip-3'),
                        ActivityId::fromUnprefixed('long-trip-4'),
                        ActivityId::fromUnprefixed('long-trip-5'),
                        ActivityId::fromUnprefixed('long-trip-6'),
                        ActivityId::fromUnprefixed('long-trip-7'),
                        ActivityId::fromUnprefixed('long-trip-8'),
                        ActivityId::fromUnprefixed('long-trip-9'),
                    ]),
                    startDate: SerializableDateTime::fromString('2024-07-01 00:00:00'),
                    endDate: SerializableDateTime::fromString('2024-08-02 00:00:00'),
                    countryCodes: [],
                    places: [],
                ),
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
