<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
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

    public function testParseSportTypeComesFromFirstTrack(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-multi-track.gpx'));

        $this->assertSame(SportType::RUN, $parsed->getActivity()->getSportType());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new GpxFileParser(
            new IncrementingActivityIdFactory(),
            new IncrementingActivityLapIdFactory(),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );
    }
}
