<?php

namespace App\Tests\Domain\Zwift\FindZwiftStatsPerWorld;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\WorldType;
use App\Domain\Zwift\FindZwiftStatsPerWorld\FindZwiftStatsPerWorld;
use App\Domain\Zwift\FindZwiftStatsPerWorld\FindZwiftStatsPerWorldQueryHandler;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class FindZwiftStatsPerWorldQueryHandlerTest extends ContainerTestCase
{
    private FindZwiftStatsPerWorldQueryHandler $queryHandler;

    public function testHandle(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('0'))
                ->withWorldType(WorldType::REAL_WORLD)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withWorldType(WorldType::ZWIFT)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withWorldType(WorldType::ZWIFT)
                ->withRouteGeography(RouteGeography::create(['state' => 'Watopia']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withWorldType(WorldType::ZWIFT)
                ->withRouteGeography(RouteGeography::create(['state' => 'Watopia']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withWorldType(WorldType::ZWIFT)
                ->withRouteGeography(RouteGeography::create(['state' => 'Makuri islands']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('8'))
                ->withWorldType(WorldType::ZWIFT)
                ->withRouteGeography(RouteGeography::create(['state' => 'New York']))
                ->build(),
            []
        ));

        /** @var \App\Domain\Zwift\FindZwiftStatsPerWorld\FindZwiftStatsPerWorldResponse $response */
        $response = $this->queryHandler->handle(new FindZwiftStatsPerWorld());
        $this->assertEquals(
            [
                [
                    'zwiftWorld' => 'Watopia',
                    'numberOfActivities' => 2,
                    'distance' => Kilometer::from(20),
                    'elevation' => Meter::from(0),
                    'movingTime' => Seconds::from(20),
                    'calories' => 0,
                ],
                [
                    'zwiftWorld' => 'New York',
                    'numberOfActivities' => 1,
                    'distance' => Kilometer::from(10),
                    'elevation' => Meter::from(0),
                    'movingTime' => Seconds::from(10),
                    'calories' => 0,
                ],
                [
                    'zwiftWorld' => 'Makuri islands',
                    'numberOfActivities' => 1,
                    'distance' => Kilometer::from(10),
                    'elevation' => Meter::from(0),
                    'movingTime' => Seconds::from(10),
                    'calories' => 0,
                ],
            ],
            $response->getStatsPerWorld(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryHandler = new FindZwiftStatsPerWorldQueryHandler($this->getConnection());
    }
}
