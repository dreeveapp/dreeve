<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
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

    public function testParseEmptyContentsThrows(): void
    {
        $rawActivityFile = RawActivityFile::from(Path::fromString('does-not-exist.gpx'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Could not read "does-not-exist.gpx"', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseUnknownSportDefaultsToWorkout(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
              <trk>
                <type>Other</type>
                <trkseg>
                  <trkpt lat="45.0" lon="22.5">
                    <time>2021-09-08T00:00:00Z</time>
                  </trkpt>
                  <trkpt lat="45.001" lon="22.501">
                    <time>2021-09-08T00:00:10Z</time>
                  </trkpt>
                </trkseg>
              </trk>
            </gpx>
            XML;

        $parsed = $this->parser->parse(RawActivityFile::from(Path::fromString('other-sport.gpx'), $xml));

        $this->assertSame(SportType::WORKOUT, $parsed->getActivity()->getSportType());
    }

    public function testParseWithoutTimedTrackpointsThrows(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
              <trk>
                <trkseg>
                  <trkpt lat="45.0" lon="22.5"><ele>100</ele></trkpt>
                </trkseg>
              </trk>
            </gpx>
            XML;

        $rawActivityFile = RawActivityFile::from(Path::fromString('no-time.gpx'), $xml);

        $this->expectExceptionObject(new CouldNotParseActivityFile('No trackpoints with a timestamp found in "no-time.gpx"', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new GpxFileParser(
            new IncrementingActivityIdFactory(),
            new IncrementingActivityLapIdFactory(),
            PausedClock::fromString('2023-10-17 16:15:04'),
            SerializableTimezone::UTC(),
        );
    }
}
