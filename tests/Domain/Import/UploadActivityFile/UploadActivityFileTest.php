<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\UploadActivityFile;

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

        $this->assertSame('ride.fit', $command->getFilename());
        $this->assertSame('raw-fit-bytes', $command->getContents());
    }

    public function testFromPayloadStripsPathTraversal(): void
    {
        $command = UploadActivityFile::fromPayload([
            'filename' => '../../etc/foo.gpx',
            'content' => base64_encode('raw-gpx-bytes'),
        ]);

        $this->assertSame('foo.gpx', $command->getFilename());
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

        yield 'malformed base64 content' => [
            ['filename' => 'ride.fit', 'content' => 'not-valid-base64!!!'],
            'The file content must be valid, non-empty base64.',
        ];

        yield 'empty content' => [
            ['filename' => 'ride.fit', 'content' => ''],
            'The file content must be valid, non-empty base64.',
        ];
    }
}
