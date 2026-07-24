<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\WorldType;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityTest extends TestCase
{
    use MatchesSnapshots;

    public function testGetName(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withName('Zwift - Test Activity #hashtag')
            ->build();

        $this->assertEquals('Test Activity #hashtag', $activity->getName());
        $this->assertEquals('Zwift - Test Activity #hashtag', $activity->getOriginalName());

        $activity = ActivityBuilder::fromDefaults()
            ->withName('Morning ride #sfs-chain-lubed #sfs-di-2-charged #fun')
            ->build();

        $this->assertEquals('Morning ride #fun', $activity->getName());
        $this->assertEquals('Morning ride #sfs-chain-lubed #sfs-di-2-charged #fun', $activity->getOriginalName());
    }

    public function testLeafletMapWithoutStartingCoordinate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline('line')
            ->withWorldType(WorldType::ZWIFT)
            ->build();

        $this->assertNull($activity->getLeafletMap());
    }

    public function testLeafletMapWhenZwiftMapCouldNotBeDetermined(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withWorldType(WorldType::ZWIFT)
            ->withStartingCoordinate(
                Coordinate::createFromLatAndLng(Latitude::fromString('1'), Longitude::fromString('1'))
            )
            ->withPolyline('line')
            ->build();

        $this->assertNull($activity->getLeafletMap());
    }

    public function testGetPaceInSecPer100Meter(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withAverageSpeed(KmPerHour::from(10))
            ->build();

        $this->assertEquals(
            SecPer100Meter::from(35.9971),
            $activity->getPaceInSecPer100Meter()
        );
    }
}
