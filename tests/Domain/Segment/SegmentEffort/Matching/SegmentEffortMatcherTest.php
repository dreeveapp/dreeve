<?php

namespace App\Tests\Domain\Segment\SegmentEffort\Matching;

use App\Domain\Segment\SegmentEffort\Matching\MatchedEffort;
use App\Domain\Segment\SegmentEffort\Matching\SegmentEffortMatcher;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\Domain\Segment\SegmentBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SegmentEffortMatcherTest extends TestCase
{
    #[DataProvider(methodName: 'provideActivities')]
    public function testMatch(string $fixture, array $expectedElapsedTimes): void
    {
        $streams = Json::decode(file_get_contents(__DIR__.'/fixtures/'.$fixture.'.json') ?: '');

        $matchedEfforts = new SegmentEffortMatcher()->match(
            segment: SegmentBuilder::fromDefaults()
                ->withDistance(Kilometer::from(1))
                ->withPolyline(EncodedPolyline::fromString(file_get_contents(__DIR__.'/fixtures/segment-polyline.txt') ?: ''))
                ->build(),
            latLng: $streams['latlng'],
            time: $streams['time'],
            distance: $streams['distance'],
            watts: $streams['watts'],
            heartRate: $streams['heartrate'],
        );

        $this->assertSame(
            $expectedElapsedTimes,
            array_map(
                static fn (MatchedEffort $matchedEffort): float => $matchedEffort->getElapsedTimeInSeconds(),
                $matchedEfforts,
            ),
        );
    }

    public static function provideActivities(): iterable
    {
        yield 'exact pass' => ['exact-pass', [125.0]];
        yield 'two laps' => ['two-laps', [125.0, 125.0]];
        yield 'reversed direction' => ['reversed', []];
        yield 'parallel road' => ['parallel-road', []];
        yield 'pause inside the segment' => ['pause-inside-segment', [186.0]];
        yield 'waits at the start' => ['waits-at-start', [125.0]];
        yield 'too short' => ['too-short', []];
    }

    public function testMatchCalculatesAverages(): void
    {
        $streams = Json::decode(file_get_contents(__DIR__.'/fixtures/exact-pass.json') ?: '');

        $matchedEfforts = new SegmentEffortMatcher()->match(
            segment: SegmentBuilder::fromDefaults()
                ->withDistance(Kilometer::from(1))
                ->withPolyline(EncodedPolyline::fromString(file_get_contents(__DIR__.'/fixtures/segment-polyline.txt') ?: ''))
                ->build(),
            latLng: $streams['latlng'],
            time: $streams['time'],
            distance: $streams['distance'],
            watts: $streams['watts'],
            heartRate: $streams['heartrate'],
        );

        $this->assertEquals(
            [new MatchedEffort(
                startIndex: 37,
                endIndex: 162,
                elapsedTimeInSeconds: 125.0,
                averageWatts: 209.5,
                averageHeartRate: 145,
                maxHeartRate: 149,
            )],
            $matchedEfforts,
        );
    }

    public function testMatchWithoutStreamData(): void
    {
        $this->assertSame([], new SegmentEffortMatcher()->match(
            segment: SegmentBuilder::fromDefaults()
                ->withPolyline(EncodedPolyline::fromString(file_get_contents(__DIR__.'/fixtures/segment-polyline.txt') ?: ''))
                ->build(),
            latLng: [],
            time: [],
            distance: [],
        ));
    }

    public function testMatchWithoutSegmentPolyline(): void
    {
        $streams = Json::decode(file_get_contents(__DIR__.'/fixtures/exact-pass.json') ?: '');

        $this->assertSame([], new SegmentEffortMatcher()->match(
            segment: SegmentBuilder::fromDefaults()->build(),
            latLng: $streams['latlng'],
            time: $streams['time'],
            distance: $streams['distance'],
        ));
    }
}
