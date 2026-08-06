<?php

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\ActivityBasedRouteRepository;
use App\Domain\Activity\Route\Route;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityBasedRouteRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private RouteRepository $routeRepository;
    private ActivityRepository $activityRepository;

    public function testFindAll(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))
                ->build(),
            rawData: []
        ));

        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
                ->withPolyline('')
                ->withRouteGeography(RouteGeography::create(['waw']))
                ->build(),
            rawData: []
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(3))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
                ->withPolyline(null)
                ->withRouteGeography(RouteGeography::create(['waw']))
                ->build(),
            rawData: []
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
                ->withPolyline('line')
                ->withRouteGeography(RouteGeography::create([]))
                ->build(),
            rawData: []
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(5))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create([
                    'country_code' => 'PL',
                    'passed_through_countries' => ['CZ', 'PL'],
                ]))
                ->build(),
            rawData: []
        ));

        $this->assertMatchesJsonSnapshot(Json::encode($this->routeRepository->findAll()));
    }

    /**
     * Activity::hasMappableRoute() reimplements this repository's WHERE clause in PHP, so that the
     * domain can tell whether a change impacts the heatmap. Guard the two against drifting apart.
     */
    public function testFindAllAgreesWithHasMappableRoute(): void
    {
        $activities = [
            'on the map' => $this->activityOnTheMap(),
            'sport type does not support reverse geocoding' => $this->activityOnTheMap()
                ->withSportType(SportType::VIRTUAL_RIDE),
            'not recorded in the real world' => $this->activityOnTheMap()
                ->withWorldType(WorldType::ZWIFT),
            'without polyline' => $this->activityOnTheMap()->withPolyline(null),
            'with empty polyline' => $this->activityOnTheMap()->withPolyline(''),
            'without a reverse geocoded country' => $this->activityOnTheMap()
                ->withRouteGeography(RouteGeography::create([])),
            'reverse geocoded without a country' => $this->activityOnTheMap()
                ->withRouteGeography(RouteGeography::create(['is_reverse_geocoded' => true])),
        ];

        $expected = [];
        $actual = [];
        foreach ($activities as $description => $builder) {
            $activityId = ActivityId::fromUnprefixed(md5($description));
            $activity = $builder->withActivityId($activityId)->build();

            $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));

            $expected[$description] = $activity->hasMappableRoute();
            $actual[$description] = $activityId;
        }

        $activityIdsOnTheMap = $this->routeRepository->findAll()->map(
            fn (Route $route): string => (string) $route->getActivityId()
        );
        $actual = array_map(
            fn (ActivityId $activityId): bool => in_array((string) $activityId, $activityIdsOnTheMap, true),
            $actual
        );

        $this->assertEquals($expected, $actual);
        // Guard against a test set that trivially agrees because nothing is on the map.
        $this->assertContains(true, $expected);
    }

    private function activityOnTheMap(): ActivityBuilder
    {
        return ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withWorldType(WorldType::REAL_WORLD)
            ->withPolyline('tqafAua~y^vG{D')
            ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->routeRepository = new ActivityBasedRouteRepository(
            $this->getConnection(),
        );
    }
}
