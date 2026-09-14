<?php

namespace App\Tests\Domain\Segment;

use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SegmentTest extends TestCase
{
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
