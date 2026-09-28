<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportOverviewItem;
use App\Domain\Import\QueuedFileImports;
use App\Domain\Import\WatchDirectory;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueuedFileImportsTest extends TestCase
{
    private Filesystem $filesystem;
    private QueuedFileImports $queuedFileImports;

    public function testFindReturnsNothingWhenTheWatchDirectoryDoesNotExist(): void
    {
        $this->assertSame([], $this->queuedFileImports->find());
    }

    public function testFindListsSupportedFilesSortedByFilename(): void
    {
        $this->filesystem->write('watch/run.tcx', 'raw-tcx-bytes');
        $this->filesystem->write('watch/ride.FIT', 'raw-fit-bytes');
        $this->filesystem->write('watch/hike.gpx', 'raw-gpx-bytes');
        $this->filesystem->write('watch/readme.txt', 'some text');
        $this->filesystem->write('watch/.uploads/staged.fit', 'raw-fit-bytes');
        $this->filesystem->createDirectory('watch/nested.fit');

        $this->assertEquals(
            [
                FileImportOverviewItem::queued('hike.gpx', ImportSource::GPX_FILE),
                FileImportOverviewItem::queued('ride.fit', ImportSource::FIT_FILE),
                FileImportOverviewItem::queued('run.tcx', ImportSource::TCX_FILE),
            ],
            $this->queuedFileImports->find()
        );
    }

    #[DataProvider('provideFilterScenarios')]
    public function testFindAppliesFilters(
        ?string $filename,
        ?ImportSource $source,
        array $expectedFilenames,
    ): void {
        $this->filesystem->write('watch/ride.fit', 'raw-fit-bytes');
        $this->filesystem->write('watch/ride-1.fit', 'raw-fit-bytes');
        $this->filesystem->write('watch/run.tcx', 'raw-tcx-bytes');

        $this->assertSame(
            $expectedFilenames,
            array_map(
                static fn (FileImportOverviewItem $item): string => $item->getOriginalFilename(),
                $this->queuedFileImports->find($filename, $source)
            )
        );
    }

    public static function provideFilterScenarios(): iterable
    {
        yield 'a filename filter matches the exact stored name' => ['ride.fit', null, ['ride.fit']];
        yield 'a source filter matches on the file extension' => [null, ImportSource::FIT_FILE, ['ride-1.fit', 'ride.fit']];
        yield 'filename and source filters combine' => ['ride.fit', ImportSource::TCX_FILE, []];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->queuedFileImports = new QueuedFileImports(new WatchDirectory(
            KernelProjectDir::fromString('/project/dir'),
            $this->filesystem,
        ));
    }
}
