<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\UploadActivityFile;

use App\Domain\Import\InvalidActivityFileName;
use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UploadActivityFileTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UploadActivityFile::fromPayload([
            'filename' => 'ride.fit',
            'content' => base64_encode('raw-fit-bytes'),
        ]);

        $this->assertSame('ride.fit', (string) $command->getFilename());
        $this->assertSame('raw-fit-bytes', $command->getContents());
    }

    #[DataProvider('provideNamesThatAreStripped')]
    public function testFromPayloadStripsPathTraversal(string $filename, string $expected): void
    {
        $command = UploadActivityFile::fromPayload([
            'filename' => $filename,
            'content' => base64_encode('raw-gpx-bytes'),
        ]);

        $this->assertSame($expected, (string) $command->getFilename());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNamesThatAreStripped(): iterable
    {
        yield 'a unix path' => ['../../etc/foo.gpx', 'foo.gpx'];
        yield 'a backslash escape' => ['..\\foo.gpx', 'foo.gpx'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UploadActivityFile::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing filename' => [
            ['content' => base64_encode('raw-fit-bytes')],
            'A "filename" and "content" are required.',
        ];

        yield 'missing content' => [
            ['filename' => 'ride.fit'],
            'A "filename" and "content" are required.',
        ];

        yield 'unsupported extension' => [
            ['filename' => 'notes.txt', 'content' => base64_encode('some text')],
            'The file type is not supported.',
        ];

        yield 'only an extension' => [
            ['filename' => '.fit', 'content' => base64_encode('raw-fit-bytes')],
            'The file name is not valid.',
        ];

        yield 'a null byte in the filename' => [
            ['filename' => "ride\0.fit", 'content' => base64_encode('raw-fit-bytes')],
            'The file name is not valid.',
        ];

        yield 'a filename longer than the limit' => [
            ['filename' => str_repeat('a', 200).'.fit', 'content' => base64_encode('raw-fit-bytes')],
            'The file name cannot be longer than 200 bytes.',
        ];

        yield 'malformed base64 content' => [
            ['filename' => 'ride.fit', 'content' => 'not-valid-base64!!!'],
            'The file content must be valid, non-empty base64.',
        ];

        yield 'empty content' => [
            ['filename' => 'ride.fit', 'content' => ''],
            'The file content must be valid, non-empty base64.',
        ];
    }

    public function testFromFile(): void
    {
        $command = UploadActivityFile::fromFile('ride.fit', 'raw-fit-bytes');

        $this->assertSame('ride.fit', (string) $command->getFilename());
        // No base64 round trip: the API path already holds the raw bytes.
        $this->assertSame('raw-fit-bytes', $command->getContents());
    }

    public function testFromFileWithAnInvalidName(): void
    {
        $this->expectException(InvalidActivityFileName::class);

        UploadActivityFile::fromFile('notes.txt', 'raw-bytes');
    }
}
