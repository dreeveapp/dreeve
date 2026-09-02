<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\FileParser\ActivityLapsMapper;
use App\Domain\Import\FileParser\ActivityStreamsMapper;
use App\Domain\Import\FileParser\CouldNotParseActivityFile;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Domain\Import\FileParser\TcxFileParser;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use App\Tests\Domain\Activity\IncrementingActivityIdFactory;
use App\Tests\Domain\Activity\Lap\IncrementingActivityLapIdFactory;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class TcxFileParserTest extends ActivityFileParserTestCase
{
    private TcxFileParser $parser;

    public function testSupportedExtensions(): void
    {
        $this->assertSame(SupportedFileExtension::TCX, $this->parser->supportedExtension());
    }

    public function testParse(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity.tcx'))
        );
    }

    public function testParseFillsGapsInSparseTrackpoints(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity-sparse.tcx'))
        );
    }

    public function testParseMergedFileCorrectsMovingTimeAndDistance(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity-merged.tcx'))
        );
    }

    public function testParsePolarExportDerivesSpeedFromDistanceAndTime(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity-polar.tcx'))
        );
    }

    public function testParseHuaweiExportDerivesSpeedAndDistanceFromCoordinates(): void
    {
        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFileFromFixture('activity-huawei.tcx'))
        );
    }

    public function testParseEmptyContentsThrows(): void
    {
        $rawActivityFile = RawActivityFile::from(Path::fromString('does-not-exist.tcx'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Could not read "does-not-exist.tcx"', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseUnknownSportDefaultsToWorkout(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-other-sport.tcx'));

        $this->assertSame(SportType::WORKOUT, $parsed->getActivity()->getSportType());
    }

    public function testParseMultipleLapsAndTracks(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-multi-lap.tcx'));

        $laps = $parsed->getLaps()->toArray();
        $this->assertCount(2, $laps);
        $this->assertSame(1, $laps[0]->getLapNumber());
        $this->assertSame('Lap 1', $laps[0]->getName());
        $this->assertSame(2, $laps[1]->getLapNumber());
        $this->assertSame('Lap 2', $laps[1]->getName());
        // Trackpoints of the second <Track> in lap 1 must not be dropped.
        $this->assertSame(60.0, $laps[0]->getDistance()->toFloat());
        $this->assertSame(
            [0, 10, 20, 30, 40],
            $parsed->getStreams()->filterOnType(StreamType::TIME)->getData()
        );
        // The Suunto zero-altitude filter leaves these zeros alone because the file has
        // no real altitude data, after which the mapper drops the stream: an altitude
        // series of nothing but zeroes conveys no elevation.
        $this->assertNull($parsed->getStreams()->filterOnType(StreamType::ALTITUDE));
    }

    public function testParseFileWithBomAndLeadingWhitespace(): void
    {
        $parsed = $this->parser->parse($this->rawFileFromFixture('activity-leading-whitespace.tcx'));
        $activity = $parsed->getActivity();

        $this->assertSame(SportType::RIDE, $activity->getSportType());
        $this->assertSame('Garmin Edge 530', $activity->getDeviceName());
        $this->assertSame(0.05, $activity->getDistance()->toFloat());
        $this->assertSame(42, $activity->getCalories());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new TcxFileParser(
            new IncrementingActivityIdFactory(),
            new ActivityLapsMapper(new IncrementingActivityLapIdFactory()),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );
    }
}
