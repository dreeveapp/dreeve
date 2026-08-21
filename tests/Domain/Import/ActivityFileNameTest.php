<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Import\ActivityFileName;
use App\Domain\Import\InvalidActivityFileName;
use App\Domain\Import\SupportedFileExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityFileNameTest extends TestCase
{
    #[DataProvider('provideNamesThatAreKept')]
    public function testFromString(string $name, string $expected): void
    {
        $this->assertSame($expected, (string) ActivityFileName::fromString($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNamesThatAreKept(): iterable
    {
        yield 'a plain name' => ['ride.fit', 'ride.fit'];
        yield 'an uppercase extension' => ['Ride_2026-07-02.FIT', 'Ride_2026-07-02.FIT'];
        yield 'spaces and accents' => ['Ochtendrit café.gpx', 'Ochtendrit café.gpx'];
        yield 'a unix path is reduced to its last segment' => ['../../etc/foo.gpx', 'foo.gpx'];
        yield 'a windows path is reduced to its last segment' => ['C:\\Users\\me\\ride.tcx', 'ride.tcx'];
        // basename() does not treat a backslash as a separator on Linux, but Flysystem resolves
        // it, so an unnormalised "..\ride.fit" lands outside the watch folder.
        yield 'a backslash escape is reduced to its last segment' => ['..\\ride.fit', 'ride.fit'];
    }

    #[DataProvider('provideInvalidNames')]
    public function testFromStringThrows(string $name, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidActivityFileName::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        ActivityFileName::fromString($name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidNames(): iterable
    {
        yield 'an unsupported extension' => ['notes.txt', 'The file type is not supported.'];
        yield 'no extension' => ['ride', 'The file type is not supported.'];
        yield 'only an extension' => ['.fit', 'The file name is not valid.'];
        yield 'an empty name' => ['', 'The file name is not valid.'];
        yield 'a name that is only a path' => ['../../', 'The file type is not supported.'];
        yield 'a null byte' => ["ride\0.fit", 'The file name is not valid.'];
        yield 'a control character' => ["ride\x1F.fit", 'The file name is not valid.'];
        yield 'an invalid utf-8 sequence' => ["ride\xC3\x28.fit", 'The file name is not valid.'];
        yield 'a name longer than the limit' => [str_repeat('a', 200).'.fit', 'The file name cannot be longer than 200 bytes.'];
    }

    public function testGetExtension(): void
    {
        $this->assertSame(
            SupportedFileExtension::FIT,
            ActivityFileName::fromString('Ride_2026-07-02.FIT')->getExtension(),
        );
    }

    public function testWithSuffix(): void
    {
        $this->assertSame(
            'Ride_2026-07-02-1.FIT',
            (string) ActivityFileName::fromString('Ride_2026-07-02.FIT')->withSuffix('1'),
        );
    }
}
