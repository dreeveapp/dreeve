<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
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
        $this->givenFitToolReturnsFixture('fit-document.json');

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFile('/tmp/activity.fit'))
        );
    }

    public function testParseDerivesSummaryMetricsFromStreamsWhenMissingFromSession(): void
    {
        $this->givenFitToolReturnsFixture('fit-document-without-session-summary-metrics.json');

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFile('/tmp/activity.fit'))
        );
    }

    public function testParseMergesRecordsSplitAcrossSameTimestamp(): void
    {
        $this->givenFitToolReturnsFixture('fit-document-with-split-records.json');

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFile('/tmp/activity.fit'))
        );
    }

    public function testParseRealFitFileThroughBinary(): void
    {
        $parser = new FitFileParser(
            new IncrementingActivityIdFactory(),
            new IncrementingActivityLapIdFactory(),
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
            new IncrementingActivityLapIdFactory(),
            new SymfonyProcessFactory(),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );

        $this->assertParsedFileMatchesSnapshot(
            $parser->parse($this->rawFileFromFixture('activity-pool-swim-with-hr-mesgs.fit'))
        );
    }

    public function testParseMergesStrapHeartRateFromHrMessages(): void
    {
        // The records carry wrist readings of 120 and 130 bpm; the strap
        // samples in the chained hr messages should replace them with 96 and
        // 98 bpm.
        $this->givenFitToolReturnsFixture('fit-document-with-hr-mesgs.json');

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFile('/tmp/activity.fit'))
        );
    }

    public function testParseIgnoresStrapHeartRateWithoutAnchor(): void
    {
        $document = $this->fitDocumentFromFixture('fit-document-with-hr-mesgs.json');
        // Strip the anchor hr message; without it the event_timestamp clock
        // cannot be mapped to wall clock time and the wrist readings are kept.
        array_shift($document['files'][1]['messages']);

        $this->givenFitToolReturns(Json::encode($document));

        $this->assertParsedFileMatchesSnapshot(
            $this->parser->parse($this->rawFile('/tmp/activity.fit'))
        );
    }

    public function testParsePrefersProductNameWhenManufacturerHasNoProductEnum(): void
    {
        $document = $this->minimalFitDocument(fileIdFields: [
            ['name' => 'manufacturer', 'value' => 23],
            ['name' => 'product', 'value' => 999],
            ['name' => 'product_name', 'value' => 'Suunto Vertical'],
        ]);
        $this->givenFitToolReturns(Json::encode($document));

        $this->assertSame('Suunto Vertical', $this->parser->parse($this->rawFile('/tmp/activity.fit'))->getActivity()->getDeviceName());
    }

    public function testParseFallsBackToManufacturerWhenProductNameMissing(): void
    {
        $document = $this->minimalFitDocument(fileIdFields: [
            ['name' => 'manufacturer', 'value' => 123],
            ['name' => 'product', 'value' => 99],
        ]);
        $this->givenFitToolReturns(Json::encode($document));

        $this->assertSame('Polar Electro', $this->parser->parse($this->rawFile('/tmp/activity.fit'))->getActivity()->getDeviceName());
    }

    public function testParseTrailRunSubSport(): void
    {
        $document = $this->minimalFitDocument(sessionFields: [
            ['name' => 'sport', 'value' => 1], // running
            ['name' => 'sub_sport', 'value' => 3], // trail
            ['name' => 'start_time', 'value' => self::START_FIT_SECONDS],
        ]);
        $this->givenFitToolReturns(Json::encode($document));

        $this->assertSame(SportType::TRAIL_RUN, $this->parser->parse($this->rawFile('/tmp/activity.fit'))->getActivity()->getSportType());
    }

    public function testParseUnsuccessfulProcessThrows(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(false);
        $process->method('getErrorOutput')->willReturn('boom');
        $this->processFactory->method('create')->willReturn($process);

        $rawActivityFile = $this->rawFile('/tmp/activity.fit');

        $this->expectExceptionObject(new CouldNotParseActivityFile('fit-tool could not decode "activity.fit": boom', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    public function testParseUnsupportedSportThrows(): void
    {
        $document = $this->minimalFitDocument(sessionFields: [
            ['name' => 'sport', 'value' => 24], // driving (unsupported)
            ['name' => 'start_time', 'value' => self::START_FIT_SECONDS],
        ]);
        $this->givenFitToolReturns(Json::encode($document));

        $rawActivityFile = $this->rawFile('/tmp/activity.fit');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Unsupported FIT sport 24 (sub sport null)', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    private function minimalFitDocument(array $fileIdFields = [], array $sessionFields = []): array
    {
        return [
            'files' => [[
                'profileVersion' => 2132,
                'messages' => [
                    ['name' => 'file_id', 'fields' => $fileIdFields],
                    ['name' => 'session', 'fields' => [] !== $sessionFields ? $sessionFields : [
                        ['name' => 'sport', 'value' => 2], // cycling
                        ['name' => 'start_time', 'value' => self::START_FIT_SECONDS],
                    ]],
                    ['name' => 'record', 'fields' => [
                        ['name' => 'timestamp', 'value' => self::START_FIT_SECONDS],
                    ]],
                ],
            ]],
        ];
    }

    private function rawFile(string $path): RawActivityFile
    {
        return RawActivityFile::from(Path::fromString($path), '');
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

    private function givenFitToolReturnsFixture(string $fixture): void
    {
        $this->givenFitToolReturns((string) file_get_contents(__DIR__.'/fixtures/'.$fixture));
    }

    private function fitDocumentFromFixture(string $fixture): array
    {
        return Json::decode((string) file_get_contents(__DIR__.'/fixtures/'.$fixture));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FitFileParser(
            new IncrementingActivityIdFactory(),
            new IncrementingActivityLapIdFactory(),
            $this->processFactory = $this->createStub(ProcessFactory::class),
            new ActivityStreamsMapper(PausedClock::fromString('2023-10-17 16:15:04')),
            SerializableTimezone::UTC(),
        );
    }
}
