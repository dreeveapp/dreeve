<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\StreamType;
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

    public function testParseEmptyContentsThrows(): void
    {
        $rawActivityFile = RawActivityFile::from(Path::fromString('does-not-exist.tcx'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Could not read "does-not-exist.tcx"', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseUnknownSportDefaultsToWorkout(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2">
              <Activities>
                <Activity Sport="Other">
                  <Id>2021-09-08T00:00:00Z</Id>
                  <Lap StartTime="2021-09-08T00:00:00Z">
                    <Track>
                      <Trackpoint>
                        <Time>2021-09-08T00:00:00Z</Time>
                        <Position><LatitudeDegrees>45.0</LatitudeDegrees><LongitudeDegrees>22.5</LongitudeDegrees></Position>
                      </Trackpoint>
                    </Track>
                  </Lap>
                </Activity>
              </Activities>
            </TrainingCenterDatabase>
            XML;

        $parsed = $this->parser->parse(RawActivityFile::from(Path::fromString('other-sport.tcx'), $xml));

        $this->assertSame(SportType::WORKOUT, $parsed->getActivity()->getSportType());
    }

    public function testParseMultipleLapsAndTracks(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2">
              <Activities>
                <Activity Sport="Running">
                  <Id>2021-09-08T00:00:00Z</Id>
                  <Lap StartTime="2021-09-08T00:00:00Z">
                    <TotalTimeSeconds>20</TotalTimeSeconds>
                    <Track>
                      <Trackpoint>
                        <Time>2021-09-08T00:00:00Z</Time>
                        <DistanceMeters>0</DistanceMeters>
                      </Trackpoint>
                      <Trackpoint>
                        <Time>2021-09-08T00:00:10Z</Time>
                        <DistanceMeters>30</DistanceMeters>
                      </Trackpoint>
                    </Track>
                    <Track>
                      <Trackpoint>
                        <Time>2021-09-08T00:00:20Z</Time>
                        <DistanceMeters>60</DistanceMeters>
                      </Trackpoint>
                    </Track>
                  </Lap>
                  <Lap StartTime="2021-09-08T00:00:30Z">
                    <TotalTimeSeconds>10</TotalTimeSeconds>
                    <Track>
                      <Trackpoint>
                        <Time>2021-09-08T00:00:30Z</Time>
                        <DistanceMeters>60</DistanceMeters>
                      </Trackpoint>
                      <Trackpoint>
                        <Time>2021-09-08T00:00:40Z</Time>
                        <DistanceMeters>100</DistanceMeters>
                      </Trackpoint>
                    </Track>
                  </Lap>
                </Activity>
              </Activities>
            </TrainingCenterDatabase>
            XML;

        $parsed = $this->parser->parse(RawActivityFile::from(Path::fromString('multi-lap.tcx'), $xml));

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
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new TcxFileParser(
            new IncrementingActivityIdFactory(),
            new IncrementingActivityLapIdFactory(),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );
    }
}
