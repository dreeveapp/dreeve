<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\FileParser\ActivityLapsMapper;
use App\Domain\Import\FileParser\ActivityStreamsMapper;
use App\Domain\Import\FileParser\CouldNotParseActivityFile;
use App\Domain\Import\FileParser\FitFileParser;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\Process\ProcessFactory;
use App\Infrastructure\Process\SymfonyProcessFactory;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use App\Tests\Domain\Activity\IncrementingActivityIdFactory;
use App\Tests\Domain\Activity\Lap\IncrementingActivityLapIdFactory;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\Process\Process;

class FitFileParserTest extends ActivityFileParserTestCase
{
    private const int START_FIT_SECONDS = 1000000000;

    private FitFileParser $parser;
    private Stub $processFactory;

    public function testSupportedExtensions(): void
    {
        $this->assertSame(SupportedFileExtension::FIT, $this->parser->supportedExtension());
    }

    public function testParse(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseWithGearChanges(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-gear-changes.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), '')),
            hasDrivetrainUsage: true,
        );
    }

    public function testParseDerivesSummaryMetricsFromStreamsWhenMissingFromSession(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-without-session-summary-metrics.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseDerivesElevationFromStreamWhenTotalAscentIsZero(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-zero-total-ascent.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseIgnoresZeroSessionHeartRate(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-zero-heart-rate.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseSessionWithoutRecords(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-session-only.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseWithoutRecordsAndWithoutSessionThrows(): void
    {
        $this->givenFitToolReturns(implode("\n", [
            Json::encode(['name' => 'file_id', 'fields' => []]),
            Json::encode(['name' => 'monitoring', 'fields' => ['timestamp' => self::START_FIT_SECONDS]]),
        ]));

        $rawActivityFile = RawActivityFile::from(Path::fromString('/tmp/activity.fit'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('No FIT "record" messages found in "activity.fit"', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParsePrefersTotalMovingTimeOverTimerTime(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-total-moving-time.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseUsesWorkoutNameAndDescription(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-workout.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseUsesStreamAveragePowerWhenSessionValueDeviatesTooMuch(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-deviating-session-avg-power.json'));

        $this->assertSame(113, $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getAveragePower());
    }

    public function testParseFixesNikeRunClubSessionUnits(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-nike-run-club.json'));

        $activity = $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity();

        $this->assertEqualsWithDelta(11.606, $activity->getAverageSpeed()->toFloat(), 0.001);
        $this->assertEqualsWithDelta(18.356, $activity->getMaxSpeed()->toFloat(), 0.001);
        $this->assertSame(70, $activity->getAverageCadence());
    }

    public function testParseMergesRecordsSplitAcrossSameTimestamp(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-split-records.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseRealFitFileThroughBinary(): void
    {
        $parser = new FitFileParser(
            new IncrementingActivityIdFactory(),
            new ActivityLapsMapper(new IncrementingActivityLapIdFactory()),
            new SymfonyProcessFactory(),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );

        $this->assertParsedFileMatchesSnapshot(
            $parser->parse($this->rawFileFromFixture('activity.fit'))
        );
    }

    public function testParseRealPoolSwimWithStrapHeartRateThroughBinary(): void
    {
        $parser = new FitFileParser(
            new IncrementingActivityIdFactory(),
            new ActivityLapsMapper(new IncrementingActivityLapIdFactory()),
            new SymfonyProcessFactory(),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );

        $this->assertParsedFileMatchesSnapshot(
            $parser->parse($this->rawFileFromFixture('activity-pool-swim-with-hr-mesgs.fit'))
        );
    }

    public function testParseIgnoresADistanceStreamThatOnlyContainsZeroes(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-zero-distance.json'));

        $streams = $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getStreams();

        $this->assertNull($streams->filterOnType(StreamType::DISTANCE));
        $this->assertNotNull($streams->filterOnType(StreamType::HEART_RATE));
    }

    public function testParseMergesStrapHeartRateFromHrMessages(): void
    {
        // The records carry wrist readings of 120 and 130 bpm; the strap
        // samples in the chained hr messages should replace them with 96 and
        // 98 bpm.
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-hr-mesgs.json'));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParseIgnoresStrapHeartRateWithoutAnchor(): void
    {
        // Strip the anchor hr message; without it the event_timestamp clock
        // cannot be mapped to wall clock time and the wrist readings are kept.
        $this->givenFitToolReturns((string) preg_replace(
            '/^\{"name":"hr","fields":\{"timestamp".*\n/m',
            '',
            (string) file_get_contents(__DIR__.'/fixtures/fit-document-with-hr-mesgs.json'),
        ));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))
        );
    }

    public function testParsePrefersProductNameWhenManufacturerHasNoProductEnum(): void
    {
        $this->givenFitToolReturns($this->minimalFitDocument(fileIdFields: [
            'manufacturer' => 23,
            'product' => 999,
            'product_name' => 'Suunto Vertical',
        ]));

        $this->assertSame('Suunto Vertical', $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getDeviceName());
    }

    public function testParseIgnoresProductNameForZwift(): void
    {
        $this->givenFitToolReturns($this->minimalFitDocument(fileIdFields: [
            'manufacturer' => 260,
            'product_name' => 'with {wo',
        ]));

        $this->assertSame('Zwift', $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getDeviceName());
    }

    public function testParseFallsBackToManufacturerWhenProductNameMissing(): void
    {
        $this->givenFitToolReturns($this->minimalFitDocument(fileIdFields: [
            'manufacturer' => 123,
            'product' => 99,
        ]));

        $this->assertSame('Polar Electro', $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getDeviceName());
    }

    public function testParseTrailRunSubSport(): void
    {
        $this->givenFitToolReturns($this->minimalFitDocument(sessionFields: [
            'sport' => 1, // running
            'sub_sport' => 3, // trail
            'start_time' => self::START_FIT_SECONDS,
        ]));

        $this->assertSame(SportType::TRAIL_RUN, $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getSportType());
    }

    public function testParseGenericSportWithCustomProfileName(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-generic-sport-profile.json'));

        $this->assertSame(SportType::WORKOUT, $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getSportType());
    }

    public function testParseResolvesGenericSportFromSportProfileName(): void
    {
        $this->givenFitToolReturns($this->minimalFitDocument(sessionFields: [
            'sport' => 0, // generic
            'sub_sport' => 0, // generic
            'sport_profile_name' => 'Indoor Rowing',
            'start_time' => self::START_FIT_SECONDS,
        ]));

        $this->assertSame(SportType::VIRTUAL_ROW, $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getSportType());
    }

    public function testParseUnsuccessfulProcessThrows(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(false);
        $process->method('getErrorOutput')->willReturn('boom');
        $this->processFactory->method('create')->willReturn($process);

        $rawActivityFile = RawActivityFile::from(Path::fromString('/tmp/activity.fit'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('fit-tool could not decode "activity.fit": boom', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseMultiSportFileThrows(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-multisport.json'));

        $rawActivityFile = RawActivityFile::from(Path::fromString('/tmp/activity.fit'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Multi-sport FIT file "activity.fit" import not supported, each leg needs to be imported as a separate file', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseMultiSportSessionThrows(): void
    {
        $this->givenFitToolReturns($this->minimalFitDocument(sessionFields: [
            'sport' => 18, // multisport
            'start_time' => self::START_FIT_SECONDS,
        ]));

        $rawActivityFile = RawActivityFile::from(Path::fromString('/tmp/activity.fit'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Multi-sport FIT file "activity.fit" import not supported, each leg needs to be imported as a separate file', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseAllowsMultipleSessionsOfTheSameSport(): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/fit-document-with-multiple-sessions-of-the-same-sport.json'));

        $this->assertSame(SportType::RIDE, $this->parser->parse(RawActivityFile::from(Path::fromString('/tmp/activity.fit'), ''))->getActivity()->getSportType());
    }

    public function testParseUnsupportedSportThrows(): void
    {
        $this->givenFitToolReturns($this->minimalFitDocument(sessionFields: [
            'sport' => 24, // driving (unsupported)
            'start_time' => self::START_FIT_SECONDS,
        ]));

        $rawActivityFile = RawActivityFile::from(Path::fromString('/tmp/activity.fit'), '');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Unsupported FIT sport 24 (sub sport null)', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    /**
     * @param array<string, int|string> $fileIdFields
     * @param array<string, int|string> $sessionFields
     */
    private function minimalFitDocument(array $fileIdFields = [], array $sessionFields = []): string
    {
        return implode("\n", [
            Json::encode(['name' => 'file_id', 'fields' => $fileIdFields]),
            Json::encode(['name' => 'session', 'fields' => [] !== $sessionFields ? $sessionFields : [
                'sport' => 2, // cycling
                'start_time' => self::START_FIT_SECONDS,
            ]]),
            Json::encode(['name' => 'record', 'fields' => [
                'timestamp' => self::START_FIT_SECONDS,
            ]]),
        ]);
    }

    private function givenFitToolReturns(string $output): void
    {
        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $process->method('getOutput')->willReturn($output);

        $this->processFactory
            ->method('create')
            ->willReturn($process);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FitFileParser(
            new IncrementingActivityIdFactory(),
            new ActivityLapsMapper(new IncrementingActivityLapIdFactory()),
            $this->processFactory = $this->createStub(ProcessFactory::class),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );
    }
}
