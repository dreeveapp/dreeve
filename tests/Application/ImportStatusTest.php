<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\ImportStatus;
use App\Domain\Import\WatchDirectory;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class ImportStatusTest extends TestCase
{
    private Filesystem $filesystem;
    private ImportStatus $importStatus;

    public function testItIsPendingWhenTheWatchDirectoryHoldsAProcessableFile(): void
    {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');

        $this->assertTrue($this->importStatus->isPending());
    }

    public function testItIsNotPendingWhenTheWatchDirectoryOnlyHoldsUnsupportedFiles(): void
    {
        $this->filesystem->write('watch/readme.txt', 'some text');

        $this->assertFalse($this->importStatus->isPending());
    }

    public function testItIsNotPendingWhenTheWatchDirectoryIsEmpty(): void
    {
        $this->filesystem->createDirectory('watch');

        $this->assertFalse($this->importStatus->isPending());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->importStatus = new ImportStatus(new WatchDirectory(
            KernelProjectDir::fromString('/project/dir'),
            $this->filesystem,
        ));
    }
}
