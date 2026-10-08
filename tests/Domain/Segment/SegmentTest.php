<?php

namespace App\Tests\Domain\Segment;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\ImportMode;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentType;
use App\Domain\Segment\SegmentWasAdded;
use App\Domain\Segment\SegmentWasDeleted;
use App\Domain\Segment\SegmentWasUpdated;
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

    public function testWithersRecordThatTheSegmentWasUpdatedOnlyOnce(): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Old name'))
            ->withSportType(SportType::RIDE)
            ->withIsFavourite(false)
            ->build();

        $updatedSegment = $segment
            ->withName(Name::fromString('New name'))
            ->withIsFavourite(true);

        $this->assertEquals(Name::fromString('New name'), $updatedSegment->getOriginalName());
        $this->assertTrue($updatedSegment->isFavourite());
        $this->assertEquals([new SegmentWasUpdated(SegmentId::fromUnprefixed('1'))], $updatedSegment->getRecordedEvents());
        $this->assertEquals(Name::fromString('Old name'), $segment->getOriginalName());
        $this->assertEmpty($segment->getRecordedEvents());
    }

    public function testWithersWithoutChangesDoNotRecordAnything(): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->withName(Name::fromString('Name'))
            ->withSportType(SportType::RIDE)
            ->withIsFavourite(true)
            ->build();

        $updatedSegment = $segment
            ->withName(Name::fromString('Name'))
            ->withIsFavourite(true);

        $this->assertSame($segment, $updatedSegment);
        $this->assertEmpty($updatedSegment->getRecordedEvents());
    }

    #[DataProvider('provideIsDeletableIn')]
    public function testIsDeletableIn(SegmentType $type, ImportMode $importMode, bool $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            SegmentBuilder::fromDefaults()->withType($type)->build()->isDeletableIn($importMode),
        );
    }

    /**
     * @return iterable<string, array{SegmentType, ImportMode, bool}>
     */
    public static function provideIsDeletableIn(): iterable
    {
        yield 'custom segment in Strava API mode' => [SegmentType::CUSTOM, ImportMode::STRAVA_API, true];
        yield 'custom segment in files mode' => [SegmentType::CUSTOM, ImportMode::FILES, true];
        yield 'imported segment in Strava API mode' => [SegmentType::IMPORTED, ImportMode::STRAVA_API, false];
        yield 'imported segment in files mode' => [SegmentType::IMPORTED, ImportMode::FILES, true];
    }

    public function testDelete(): void
    {
        $segment = SegmentBuilder::fromDefaults()->withSegmentId(SegmentId::fromUnprefixed('1'))->build();

        $segment->delete();

        $this->assertEquals([new SegmentWasDeleted(SegmentId::fromUnprefixed('1'))], $segment->getRecordedEvents());
    }

    public function testGetStravaUrl(): void
    {
        $this->assertEquals(
            'https://www.strava.com/segments/1',
            SegmentBuilder::fromDefaults()->withSegmentId(SegmentId::fromUnprefixed('1'))->build()->getStravaUrl()
        );
        $this->assertNull(SegmentBuilder::fromDefaults()->withType(SegmentType::CUSTOM)->build()->getStravaUrl());
    }

    #[DataProvider('provideIsKOM')]
    public function testIsKOM(SegmentType $type, bool $expectedIsKOM): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('12128917'))
            ->withType($type)
            ->build();

        $this->assertSame($expectedIsKOM, $segment->isKOM());
    }

    /**
     * @return iterable<string, array{SegmentType, bool}>
     */
    public static function provideIsKOM(): iterable
    {
        yield 'imported' => [SegmentType::IMPORTED, true];
        yield 'custom' => [SegmentType::CUSTOM, false];
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
