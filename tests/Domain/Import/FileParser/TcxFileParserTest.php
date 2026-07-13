<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new TcxFileParser(
            new IncrementingActivityIdFactory(),
            new IncrementingActivityLapIdFactory(),
            PausedClock::fromString('2023-10-17 16:15:04'),
            SerializableTimezone::UTC(),
        );
    }
}
