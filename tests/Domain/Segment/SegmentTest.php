<?php

namespace App\Tests\Domain\Segment;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentType;
use App\Domain\Segment\SegmentWasAdded;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SegmentTest extends TestCase
{
    public function testCreateCustom(): void
    {
        $segment = Segment::createCustom(
            segmentId: SegmentId::fromUnprefixed('custom'),
            name: Name::fromString('Custom segment'),
            sportType: SportType::RIDE,
            distance: Kilometer::from(1.2),
            maxGradient: 8.1,
            averageGradient: 4.3,
            isFavourite: false,
            countryCode: 'BE',
            polyline: EncodedPolyline::fromString('_p~iF~ps|U_ulLnnqC'),
        );

        $this->assertEquals(SegmentType::CUSTOM, $segment->getType());
        $this->assertTrue($segment->detailsHaveBeenImported());
        $this->assertNull($segment->getDeviceName());
        $this->assertNull($segment->getClimbCategory());
        $this->assertEquals(
            Coordinate::createFromLatAndLng(Latitude::fromString('38.5'), Longitude::fromString('-120.2')),
            $segment->getStartingCoordinate()
        );
        $this->assertEquals([new SegmentWasAdded()], $segment->getRecordedEvents());
    }

    public function testGetWindAheadUrl(): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->withName(Name::fromString('Oude Kwaremont & Paterberg'))
            ->withIsFavourite(true)
            ->withPolyline(EncodedPolyline::fromString('_p~iF~ps|U_ulLnnqC'))
            ->build();

        $this->assertEquals(
            'https://windahead.app/#polyline=_p~iF~ps%7CU_ulLnnqC&name=Oude%20Kwaremont%20%26%20Paterberg',
            $segment->getWindAheadUrl()
        );
    }

    public function testGetWindAheadUrlWithoutPolyline(): void
    {
        $this->assertNull(SegmentBuilder::fromDefaults()->build()->getWindAheadUrl());
    }

    #[DataProvider('provideVirtualDeviceNames')]
    public function testGetWindAheadUrlForVirtualSegment(string $deviceName): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->withDeviceName($deviceName)
            ->withPolyline(EncodedPolyline::fromString('_p~iF~ps|U_ulLnnqC'))
            ->build();

        $this->assertNull($segment->getWindAheadUrl());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideVirtualDeviceNames(): iterable
    {
        yield 'Zwift' => ['Zwift'];
        yield 'Rouvy' => ['Rouvy'];
        yield 'MyWhoosh' => ['MyWhoosh'];
    }
}
