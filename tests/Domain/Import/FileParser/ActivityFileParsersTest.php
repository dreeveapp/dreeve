<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Import\FileParser\ActivityFileParser;
use App\Domain\Import\FileParser\ActivityFileParsers;
use App\Domain\Import\FileParser\CouldNotParseActivityFile;
use App\Domain\Import\FileParser\ParsedActivityFile;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Domain\Import\FileParser\UnsupportedFileType;
use App\Domain\Import\SupportedFileExtension;
use App\Infrastructure\ValueObject\String\Path;
use PHPUnit\Framework\TestCase;

class ActivityFileParsersTest extends TestCase
{
    private ActivityFileParsers $registry;

    public function testParseRoutesToFitParserCaseInsensitive(): void
    {
        $this->expectExceptionObject(new \LogicException('parsed-by-fit'));

        $this->registry->parse($this->rawFile('/import/activity.FIT'));
    }

    public function testParseRoutesToTcxParser(): void
    {
        $this->expectExceptionObject(new \LogicException('parsed-by-tcx'));

        $this->registry->parse($this->rawFile('/import/sub/dir/ride.tcx'));
    }

    public function testParseRoutesToGpxParser(): void
    {
        $this->expectExceptionObject(new \LogicException('parsed-by-gpx'));

        $this->registry->parse($this->rawFile('/import/sub/dir/ride.gpx'));
    }

    public function testParseWithoutExtensionThrows(): void
    {
        $rawActivityFile = $this->rawFile('/import/activity');

        $this->expectExceptionObject(new CouldNotParseActivityFile('Could not determine file extension for "/import/activity"', $rawActivityFile));
        $this->registry->parse($rawActivityFile);
    }

    public function testParseWithUnsupportedExtensionThrows(): void
    {
        $this->expectExceptionObject(new UnsupportedFileType('No parser available for file extension "./import/activity.lol"'));
        $this->registry->parse($this->rawFile('/import/activity.lol'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ActivityFileParsers([
            $this->createParser(SupportedFileExtension::FIT),
            $this->createParser(SupportedFileExtension::TCX),
            $this->createParser(SupportedFileExtension::GPX),
        ]);
    }

    private function rawFile(string $path): RawActivityFile
    {
        return RawActivityFile::from(Path::fromString($path), '');
    }

    private function createParser(SupportedFileExtension $extension): ActivityFileParser
    {
        return new readonly class($extension) implements ActivityFileParser {
            public function __construct(private SupportedFileExtension $extension)
            {
            }

            public function supportedExtension(): SupportedFileExtension
            {
                return $this->extension;
            }

            public function parse(RawActivityFile $file): ParsedActivityFile
            {
                throw new \LogicException('parsed-by-'.$this->extension->value);
            }
        };
    }
}
