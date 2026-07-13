<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\SportType\SportType;
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
        $this->givenFitToolReturns(Json::encode($this->fitDocument()));

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
            PausedClock::fromString('2023-10-17 16:15:04'),
            SerializableTimezone::UTC(),
        );

        $this->assertParsedFileMatchesSnapshot(
            $parser->parse($this->rawFileFromFixture('activity.fit'))
        );
    }

    public function testParsePrefersProductNameWhenManufacturerHasNoProductEnum(): void
    {
        $document = $this->withFileId([
            ['name' => 'manufacturer', 'value' => 23],
            ['name' => 'product', 'value' => 999],
            ['name' => 'product_name', 'value' => 'Suunto Vertical'],
        ]);
        $this->givenFitToolReturns(Json::encode($document));

        $this->assertSame('Suunto Vertical', $this->parser->parse($this->rawFile('/tmp/activity.fit'))->getActivity()->getDeviceName());
    }

    public function testParseFallsBackToManufacturerWhenProductNameMissing(): void
    {
        $document = $this->withFileId([
            ['name' => 'manufacturer', 'value' => 123],
            ['name' => 'product', 'value' => 99],
        ]);
        $this->givenFitToolReturns(Json::encode($document));

        $this->assertSame('Polar Electro', $this->parser->parse($this->rawFile('/tmp/activity.fit'))->getActivity()->getDeviceName());
    }

    public function testParseTrailRunSubSport(): void
    {
        $document = $this->fitDocument();
        foreach ($document['files'][0]['messages'] as &$message) {
            if ('session' === $message['name']) {
                $message['fields'] = [
                    ['name' => 'sport', 'value' => 1], // running
                    ['name' => 'sub_sport', 'value' => 3], // trail
                    ['name' => 'start_time', 'value' => self::START_FIT_SECONDS],
                ];
            }
        }
        unset($message);
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
        $document = $this->fitDocument();
        foreach ($document['files'][0]['messages'] as &$message) {
            if ('session' === $message['name']) {
                $message['fields'] = [
                    ['name' => 'sport', 'value' => 8], // tennis (unsupported)
                    ['name' => 'start_time', 'value' => self::START_FIT_SECONDS],
                ];
            }
        }
        unset($message);
        $this->givenFitToolReturns(Json::encode($document));

        $rawActivityFile = $this->rawFile('/tmp/activity.fit');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Unsupported FIT sport 8 (sub sport null)', $rawActivityFile));
        $this->parser->parse($rawActivityFile);
    }

    /**
     * @param list<array{name: string, value: mixed}> $fields
     *
     * @return array<string, mixed>
     */
    private function withFileId(array $fields): array
    {
        $document = $this->fitDocument();
        foreach ($document['files'][0]['messages'] as &$message) {
            if ('file_id' === $message['name']) {
                $message['fields'] = $fields;
            }
        }
        unset($message);

        return $document;
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

    private function fitDocument(): array
    {
        $lat45 = 2 ** 29; // 45 degrees in semicircles
        $lng225 = 2 ** 28; // 22.5 degrees in semicircles

        return [
            'files' => [[
                'profileVersion' => 2132,
                'messages' => [
                    ['name' => 'file_id', 'fields' => [
                        ['name' => 'product_name', 'value' => 'Edge 530'],
                    ]],
                    ['name' => 'session', 'fields' => [
                        ['name' => 'sport', 'value' => 2], // cycling
                        ['name' => 'sub_sport', 'value' => 7], // road
                        ['name' => 'start_time', 'value' => self::START_FIT_SECONDS],
                        ['name' => 'start_position_lat', 'value' => $lat45],
                        ['name' => 'start_position_long', 'value' => $lng225],
                        ['name' => 'total_distance', 'value' => 50.0],
                        ['name' => 'total_ascent', 'value' => 10],
                        ['name' => 'total_timer_time', 'value' => 10],
                        ['name' => 'total_elapsed_time', 'value' => 12],
                        ['name' => 'enhanced_avg_speed', 'value' => 5.0],
                        ['name' => 'enhanced_max_speed', 'value' => 6.0],
                        ['name' => 'avg_heart_rate', 'value' => 125],
                        ['name' => 'max_heart_rate', 'value' => 140],
                        ['name' => 'avg_cadence', 'value' => 82],
                        ['name' => 'avg_power', 'value' => 205],
                        ['name' => 'max_power', 'value' => 300],
                        ['name' => 'total_calories', 'value' => 42],
                        ['name' => 'total_work', 'value' => 210000],
                    ]],
                    ['name' => 'lap', 'fields' => [
                        ['name' => 'start_time', 'value' => self::START_FIT_SECONDS],
                        ['name' => 'total_distance', 'value' => 50.0],
                        ['name' => 'total_timer_time', 'value' => 10],
                        ['name' => 'total_elapsed_time', 'value' => 12],
                        ['name' => 'enhanced_avg_speed', 'value' => 5.0],
                        ['name' => 'enhanced_max_speed', 'value' => 6.0],
                        ['name' => 'total_ascent', 'value' => 10],
                        ['name' => 'avg_heart_rate', 'value' => 125],
                    ]],
                    ['name' => 'record', 'fields' => [
                        ['name' => 'timestamp', 'value' => self::START_FIT_SECONDS],
                        ['name' => 'distance', 'value' => 0.0],
                        ['name' => 'position_lat', 'value' => 0],
                        ['name' => 'position_long', 'value' => 0],
                        ['name' => 'enhanced_altitude', 'value' => 100.0],
                        ['name' => 'enhanced_speed', 'value' => 5.0],
                        ['name' => 'heart_rate', 'value' => 120],
                        ['name' => 'cadence', 'value' => 80],
                        ['name' => 'power', 'value' => 200],
                        ['name' => 'temperature', 'value' => 20],
                    ]],
                    ['name' => 'record', 'fields' => [
                        ['name' => 'timestamp', 'value' => self::START_FIT_SECONDS + 10],
                        ['name' => 'distance', 'value' => 50.0],
                        ['name' => 'position_lat', 'value' => $lat45],
                        ['name' => 'position_long', 'value' => $lng225],
                        ['name' => 'enhanced_altitude', 'value' => 110.0],
                        ['name' => 'enhanced_speed', 'value' => 6.0],
                        ['name' => 'heart_rate', 'value' => 130],
                        ['name' => 'cadence', 'value' => 84],
                        ['name' => 'power', 'value' => 250],
                        ['name' => 'temperature', 'value' => 21],
                    ]],
                ],
            ]],
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FitFileParser(
            new IncrementingActivityIdFactory(),
            new IncrementingActivityLapIdFactory(),
            $this->processFactory = $this->createStub(ProcessFactory::class),
            PausedClock::fromString('2023-10-17 16:15:04'),
            SerializableTimezone::UTC(),
        );
    }
}
