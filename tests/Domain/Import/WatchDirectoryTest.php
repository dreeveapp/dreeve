<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Import\WatchDirectory;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\String\Path;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use PHPUnit\Framework\TestCase;

class WatchDirectoryTest extends TestCase
{
    private Filesystem $filesystem;
    private WatchDirectory $watchDirectory;

    public function testExistsWhenFolderIsPresent(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');

        $this->assertTrue($this->watchDirectory->exists());
    }

    public function testExistsWhenFolderIsAbsent(): void
    {
        $this->assertFalse($this->watchDirectory->exists());
    }

    public function testHasFilesThatCanBeProcessed(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');
        $this->filesystem->write('watch/ride.tcx', 'raw-tcx-bytes');
        $this->filesystem->write('watch/ride.gpx', 'raw-gpx-bytes');

        $this->assertTrue($this->watchDirectory->hasFilesThatCanBeProcessed());
    }

    public function testHasFilesThatCanBeProcessedWhenOnlyUnsupportedFilesArePresent(): void
    {
        $this->filesystem->write('watch/readme.txt', 'some text');
        $this->filesystem->write('watch/picture.jpg', 'some bytes');

        $this->assertFalse($this->watchDirectory->hasFilesThatCanBeProcessed());
    }

    public function testHasFilesThatCanBeProcessedWhenEmpty(): void
    {
        $this->filesystem->createDirectory('watch');

        $this->assertFalse($this->watchDirectory->hasFilesThatCanBeProcessed());
    }

    public function testListFilesOnlyReturnsFiles(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');
        $this->filesystem->write('watch/run.tcx', 'raw-tcx-bytes');
        $this->filesystem->createDirectory('watch/nested');

        $paths = $this->watchDirectory->listFiles()
            ->map(fn (StorageAttributes $file): string => $file->path())
            ->toArray();
        sort($paths);

        $this->assertSame(['watch/ride.fit', 'watch/run.tcx'], $paths);
    }

    public function testReadFile(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');

        $this->assertSame(
            'raw-fit-bytes',
            $this->watchDirectory->readFile(Path::fromString('ride.fit')),
        );
    }

    public function testReadFilePreservesUppercaseExtension(): void
    {
        $this->filesystem->write('watch/Ride_2026-07-02.FIT', 'raw-fit-bytes');

        $this->assertSame(
            'raw-fit-bytes',
            $this->watchDirectory->readFile(Path::fromString('/project/dir/watch/Ride_2026-07-02.FIT')),
        );
    }

    public function testWriteFile(): void
    {
        $this->watchDirectory->writeFile('ride.fit', 'raw-fit-bytes');

        $this->assertSame('raw-fit-bytes', $this->filesystem->read('watch/ride.fit'));
    }

    public function testDeleteFile(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');

        $this->watchDirectory->deleteFile(Path::fromString('ride.fit'));

        $this->assertFalse($this->filesystem->fileExists('watch/ride.fit'));
    }

    public function testDeleteFilePreservesUppercaseExtension(): void
    {
        $this->filesystem->write('watch/Ride_2026-07-02.FIT', 'raw-fit-bytes');

        $this->watchDirectory->deleteFile(Path::fromString('/project/dir/watch/Ride_2026-07-02.FIT'));

        $this->assertFalse($this->filesystem->fileExists('watch/Ride_2026-07-02.FIT'));
    }

    public function testGetAbsolutePathFor(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');

        $file = $this->watchDirectory->listFiles()->toArray()[0];

        $this->assertEquals(
            Path::fromString('/project/dir/watch/ride.fit'),
            $this->watchDirectory->getAbsolutePathFor($file),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->watchDirectory = new WatchDirectory(
            KernelProjectDir::fromString('/project/dir'),
            $this->filesystem,
        );
    }
}
