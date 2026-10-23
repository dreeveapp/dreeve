<?php

namespace App\Tests\Domain\Zwift\FindZwiftStatsPerWorld;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\WorldType;
use App\Domain\Zwift\FindZwiftStatsPerWorld\FindZwiftStatsPerWorld;
use App\Domain\Zwift\FindZwiftStatsPerWorld\FindZwiftStatsPerWorldQueryHandler;
use App\Domain\Zwift\ZwiftMap;
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
                ->withRouteGeography(RouteGeography::create(['state' => 'watopia']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withWorldType(WorldType::ZWIFT)
                ->withRouteGeography(RouteGeography::create(['state' => 'watopia']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withWorldType(WorldType::ZWIFT)
                ->withRouteGeography(RouteGeography::create(['state' => 'makuri-islands']))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('8'))
                ->withWorldType(WorldType::ZWIFT)
                ->withRouteGeography(RouteGeography::create(['state' => 'new-york']))
                ->build(),
            []
        ));

        /** @var \App\Domain\Zwift\FindZwiftStatsPerWorld\FindZwiftStatsPerWorldResponse $response */
        $response = $this->queryHandler->handle(new FindZwiftStatsPerWorld());
        $this->assertEquals(
            [
                [
                    'zwiftWorld' => ZwiftMap::fromId('watopia'),
                    'numberOfActivities' => 2,
                    'distance' => Kilometer::from(20),
                    'elevation' => Meter::from(0),
                    'movingTime' => Seconds::from(20),
                    'calories' => 0,
                ],
                [
                    'zwiftWorld' => ZwiftMap::fromId('new-york'),
                    'numberOfActivities' => 1,
                    'distance' => Kilometer::from(10),
                    'elevation' => Meter::from(0),
                    'movingTime' => Seconds::from(10),
                    'calories' => 0,
                ],
                [
                    'zwiftWorld' => ZwiftMap::fromId('makuri-islands'),
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
