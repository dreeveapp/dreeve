<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\Overview;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\DbalFileImportRepository;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\Overview\DbalFileImportOverviewRepository;
use App\Domain\Import\Overview\FileImportOverviewFilters;
use App\Domain\Import\Overview\FileImportOverviewItem;
use App\Domain\Import\Overview\FileImportOverviewRepository;
use App\Domain\Import\WatchDirectory;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Import\FileImportBuilder;
use App\Tests\Infrastructure\Eventing\SpyEventBus;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

class DbalFileImportOverviewRepositoryTest extends ContainerTestCase
{
    private FileImportOverviewRepository $fileImportOverviewRepository;
    private FileImportRepository $fileImportRepository;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $filesystem;

    public function testFindMapsRowToOverviewItemWithoutFileContents(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('42'))
                ->withName('Morning Run')
                ->build(),
            ['raw' => 'data'],
        ));
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withOriginalFilename('morning-run.fit')
                ->withFileContents('raw fit bytes')
                ->withSource(ImportSource::GPX_FILE)
                ->withStatus(FileImportStatus::FAILED)
                ->withErrorMessage('Could not parse file')
                ->withActivityId(ActivityId::fromUnprefixed('42'))
                ->withImportedOn(SerializableDateTime::fromString('2026-06-04 10:00:00'))
                ->build()
        );

        $overview = $this->fileImportOverviewRepository->find(
            Pagination::fromOffsetAndLimit(0, 10),
            FileImportOverviewFilters::fromRequest(new Request())
        );

        $this->assertEquals(
            [
                FileImportOverviewItem::fromState(
                    fileImportId: FileImportId::fromUnprefixed('1'),
                    originalFilename: 'morning-run.fit',
                    source: ImportSource::GPX_FILE,
                    status: FileImportStatus::FAILED,
                    importedOn: SerializableDateTime::fromString('2026-06-04 10:00:00'),
                    errorMessage: 'Could not parse file',
                    activityId: ActivityId::fromUnprefixed('42'),
                    activityName: 'Morning Run',
                    hasFileContents: true,
                ),
            ],
            $overview->getItems()
        );
        $this->assertEquals(1, $overview->getTotal());
    }

    #[DataProvider('providePaginationScenarios')]
    public function testFindOrdersByImportedOnDescAndPaginates(
        Pagination $pagination,
        array $expectedFilenames,
        int $expectedTotal,
    ): void {
        $this->seedThreeFileImports();

        $overview = $this->fileImportOverviewRepository->find($pagination, FileImportOverviewFilters::fromRequest(new Request()));

        $this->assertSame(
            $expectedFilenames,
            array_map(
                static fn (FileImportOverviewItem $item): string => $item->getOriginalFilename(),
                $overview->getItems()
            )
        );
        $this->assertEquals($expectedTotal, $overview->getTotal());
        $this->assertEquals($pagination, $overview->getPagination());
    }

    public static function providePaginationScenarios(): iterable
    {
        yield 'first page is ordered most recent first' => [
            Pagination::fromOffsetAndLimit(0, 2),
            ['newest.fit', 'middle.fit'],
            3,
        ];

        yield 'second page returns the remainder while total stays the same' => [
            Pagination::fromOffsetAndLimit(2, 2),
            ['oldest.fit'],
            3,
        ];

        yield 'a single page can hold everything' => [
            Pagination::fromOffsetAndLimit(0, 10),
            ['newest.fit', 'middle.fit', 'oldest.fit'],
            3,
        ];

        yield 'an offset past the end yields no items but still reports the total' => [
            Pagination::fromOffsetAndLimit(10, 10),
            [],
            3,
        ];
    }

    #[DataProvider('provideFileContentsScenarios')]
    public function testFindFlagsWhetherTheOriginalFileIsStillStored(
        ?string $fileContents,
        bool $expectedHasFileContents,
    ): void {
        $fileImportId = FileImportId::fromUnprefixed('1');
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId($fileImportId)
                ->withFileContents($fileContents)
                ->build()
        );

        $overview = $this->fileImportOverviewRepository->find(
            Pagination::fromOffsetAndLimit(0, 10),
            FileImportOverviewFilters::fromRequest(new Request())
        );

        $this->assertSame($expectedHasFileContents, $overview->getItems()[0]->hasFileContents());
        $this->assertSame(
            $expectedHasFileContents,
            $this->fileImportOverviewRepository->findOneByFileImportId($fileImportId)->hasFileContents()
        );
    }

    public static function provideFileContentsScenarios(): iterable
    {
        yield 'an import that kept its original file' => ['raw fit bytes', true];
        yield 'a skipped import that never stored one' => [null, false];
    }

    public function testFindReturnsAnEmptyOverviewWhenThereIsNoData(): void
    {
        $overview = $this->fileImportOverviewRepository->find(
            Pagination::fromOffsetAndLimit(0, 10),
            FileImportOverviewFilters::fromRequest(new Request())
        );

        $this->assertTrue($overview->isEmpty());
        $this->assertSame([], $overview->getItems());
        $this->assertEquals(0, $overview->getTotal());
    }

    #[DataProvider('provideFilterScenarios')]
    public function testFindAppliesFilters(
        array $filters,
        Pagination $pagination,
        array $expectedFilenames,
        int $expectedTotal,
    ): void {
        $this->seedThreeFileImports();

        $overview = $this->fileImportOverviewRepository->find(
            $pagination,
            FileImportOverviewFilters::fromRequest(new Request(query: ['filters' => $filters]))
        );

        $this->assertSame(
            $expectedFilenames,
            array_map(
                static fn (FileImportOverviewItem $item): string => $item->getOriginalFilename(),
                $overview->getItems()
            )
        );
        $this->assertEquals($expectedTotal, $overview->getTotal());
    }

    public static function provideFilterScenarios(): iterable
    {
        yield 'a status filter narrows both the items and the total' => [
            ['status' => 'success'],
            Pagination::fromOffsetAndLimit(0, 10),
            ['newest.fit', 'middle.fit'],
            2,
        ];

        yield 'a status filter combines with pagination while the total keeps reflecting the filter' => [
            ['status' => 'success'],
            Pagination::fromOffsetAndLimit(0, 1),
            ['newest.fit'],
            2,
        ];

        yield 'a source filter narrows the items' => [
            ['source' => 'tcxFile'],
            Pagination::fromOffsetAndLimit(0, 10),
            ['middle.fit'],
            1,
        ];

        yield 'status and source filters combine' => [
            ['status' => 'success', 'source' => 'fitFile'],
            Pagination::fromOffsetAndLimit(0, 10),
            ['newest.fit'],
            1,
        ];

        yield 'filters that match nothing yield an empty overview' => [
            ['status' => 'failed', 'source' => 'fitFile'],
            Pagination::fromOffsetAndLimit(0, 10),
            [],
            0,
        ];

        yield 'an invalid filter value behaves as if no filter was applied' => [
            ['status' => 'bogus'],
            Pagination::fromOffsetAndLimit(0, 10),
            ['newest.fit', 'middle.fit', 'oldest.fit'],
            3,
        ];
    }

    #[DataProvider('provideQueuedPaginationScenarios')]
    public function testFindListsQueuedFilesBeforeImportedOnes(
        Pagination $pagination,
        array $expectedFilenames,
    ): void {
        $this->seedThreeFileImports();
        $this->filesystem->write('watch/queued-b.fit', 'raw-fit-bytes');
        $this->filesystem->write('watch/queued-a.gpx', 'raw-gpx-bytes');

        $overview = $this->fileImportOverviewRepository->find($pagination, FileImportOverviewFilters::fromRequest(new Request()));

        $this->assertSame(
            $expectedFilenames,
            array_map(
                static fn (FileImportOverviewItem $item): string => $item->getOriginalFilename(),
                $overview->getItems()
            )
        );
        $this->assertEquals(5, $overview->getTotal());
    }

    public static function provideQueuedPaginationScenarios(): iterable
    {
        yield 'a page holding only queued files' => [
            Pagination::fromOffsetAndLimit(0, 2),
            ['queued-a.gpx', 'queued-b.fit'],
        ];

        yield 'a page straddling queued and imported files' => [
            Pagination::fromOffsetAndLimit(1, 2),
            ['queued-b.fit', 'newest.fit'],
        ];

        yield 'a page holding only imported files' => [
            Pagination::fromOffsetAndLimit(3, 2),
            ['middle.fit', 'oldest.fit'],
        ];
    }

    public function testFindMapsAQueuedFile(): void
    {
        $this->filesystem->write('watch/queued.tcx', 'raw-tcx-bytes');

        $this->assertEquals(
            [FileImportOverviewItem::queued('queued.tcx', ImportSource::TCX_FILE)],
            $this->fileImportOverviewRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                FileImportOverviewFilters::fromRequest(new Request())
            )->getItems()
        );
    }

    public function testFindIgnoresFiltersForQueuedFiles(): void
    {
        $this->seedThreeFileImports();
        $this->filesystem->write('watch/queued.tcx', 'raw-tcx-bytes');

        $overview = $this->fileImportOverviewRepository->find(
            Pagination::fromOffsetAndLimit(0, 10),
            FileImportOverviewFilters::fromRequest(new Request(query: ['filters' => ['status' => 'failed', 'source' => 'fitFile']]))
        );

        $this->assertSame(
            ['queued.tcx'],
            array_map(
                static fn (FileImportOverviewItem $item): string => $item->getOriginalFilename(),
                $overview->getItems()
            )
        );
        $this->assertEquals(1, $overview->getTotal());
    }

    private function seedThreeFileImports(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withOriginalFilename('oldest.fit')
                ->withSource(ImportSource::GPX_FILE)
                ->withStatus(FileImportStatus::FAILED)
                ->withImportedOn(SerializableDateTime::fromString('2026-06-01 08:00:00'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('2'))
                ->withOriginalFilename('middle.fit')
                ->withSource(ImportSource::TCX_FILE)
                ->withStatus(FileImportStatus::SUCCESS)
                ->withImportedOn(SerializableDateTime::fromString('2026-06-02 08:00:00'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('3'))
                ->withOriginalFilename('newest.fit')
                ->withSource(ImportSource::FIT_FILE)
                ->withStatus(FileImportStatus::SUCCESS)
                ->withImportedOn(SerializableDateTime::fromString('2026-06-03 08:00:00'))
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fileImportRepository = new DbalFileImportRepository(
            $this->getConnection()
        );
        $this->fileImportOverviewRepository = new DbalFileImportOverviewRepository(
            $this->getConnection(),
            $this->getContainer()->get(WatchDirectory::class),
        );
        $this->filesystem = $this->getContainer()->get(FilesystemOperator::class);
        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection(),
            new SpyEventBus(),
        );
    }
}
