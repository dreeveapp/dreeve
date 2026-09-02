<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\FileParser\ActivityLapsMapper;
use App\Domain\Import\FileParser\ActivityStreamsMapper;
use App\Domain\Import\FileParser\CouldNotParseActivityFile;
use App\Domain\Import\FileParser\GpxFileParser;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use App\Tests\Domain\Activity\IncrementingActivityIdFactory;
use App\Tests\Domain\Activity\Lap\IncrementingActivityLapIdFactory;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class GpxFileParserTest extends ActivityFileParserTestCase
{
    private GpxFileParser $parser;

    public function testSupportedExtensions(): void
    {
        $this->assertSame(SupportedFileExtension::GPX, $this->parser->supportedExtension());
    }

    public function testParse(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity.gpx'))
        );
    }

    public function testParseFillsGapsInSparseTrackpoints(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity-sparse.gpx'))
        );
    }

    public function testParseEmptyContentsThrows(): void
    {
        $rawActivityFile = RawActivityFile::from(Path::fromString('does-not-exist.gpx'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Could not read "does-not-exist.gpx"', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseUnknownSportDefaultsToWorkout(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-other-sport.gpx'));

        $this->assertSame(SportType::WORKOUT, $parsed->getActivity()->getSportType());
    }

    public function testParseWithoutTimedTrackpointsThrows(): void
    {
        $rawActivityFile = $this->rawFileFromFixture('activity-without-timed-trackpoints.gpx');

        $this->expectExceptionObject(new CouldNotParseActivityFile('No trackpoints with a timestamp found in "activity-without-timed-trackpoints.gpx"', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseMovingTimeExcludesRecordingGaps(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-with-recording-gap.gpx'));

        $this->assertSame(310, $parsed->getActivity()->getElapsedTimeInSeconds());
        // The 290s recording gap must not count as moving time.
        $this->assertSame(20, $parsed->getActivity()->getMovingTimeInSeconds());
    }

    public function testParseUsesWorkoutSummaryFromMetadata(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity-fitotrack.gpx'))
        );
    }

    public function testParseIgnoresWorkoutSummaryAsActivityName(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-fitotrack.gpx'));

        $this->assertSame('Morning Workout', $parsed->getActivity()->getName());
        $this->assertSame('Walked to the market', $parsed->getActivity()->getDescription());
    }

    public function testParseUsesMetadataNameAndDescription(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-metadata-name-and-description.gpx'));

        $this->assertSame('Loop around the lake', $parsed->getActivity()->getName());
        $this->assertSame('Easy recovery spin, stopped for coffee halfway.', $parsed->getActivity()->getDescription());
    }

    public function testParseFallsBackToStreamValuesWhenWorkoutSummaryIsImplausible(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-workout-summary-implausible.gpx'));
        $activity = $parsed->getActivity();

        $this->assertSame('Fallback name', $activity->getName());
        $this->assertSame('', $activity->getDescription());
        $this->assertNull($activity->getCalories());
        $this->assertSame(60, $activity->getElapsedTimeInSeconds());
        $this->assertSame(60, $activity->getMovingTimeInSeconds());
        $this->assertSame(0.131, $activity->getDistance()->toFloat());
    }

    public function testParseSportTypeComesFromFirstTrack(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-multi-track.gpx'));

        $this->assertSame(SportType::RUN, $parsed->getActivity()->getSportType());
    }

    public function testParseFileWithBomAndLeadingWhitespace(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-leading-whitespace.gpx'));
        $activity = $parsed->getActivity();

        $this->assertSame(SportType::RIDE, $activity->getSportType());
        $this->assertSame('Garmin Edge 530', $activity->getDeviceName());
        $this->assertSame('Morning Ride', $activity->getName());
        $this->assertSame(42, $activity->getCalories());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new GpxFileParser(
            new IncrementingActivityIdFactory(),
            new ActivityLapsMapper(new IncrementingActivityLapIdFactory()),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );
    }
}
