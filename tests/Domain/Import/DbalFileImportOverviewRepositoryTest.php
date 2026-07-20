<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\DbalFileImportOverviewRepository;
use App\Domain\Import\DbalFileImportRepository;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportOverviewItem;
use App\Domain\Import\FileImportOverviewRepository;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class DbalFileImportOverviewRepositoryTest extends ContainerTestCase
{
    private FileImportOverviewRepository $fileImportOverviewRepository;
    private FileImportRepository $fileImportRepository;
    private ActivityRepository $activityRepository;

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

        $overview = $this->fileImportOverviewRepository->find(Pagination::fromOffsetAndLimit(0, 10));

        $this->assertEquals(
            [
                FileImportOverviewItem::fromState(
                    originalFilename: 'morning-run.fit',
                    source: ImportSource::GPX_FILE,
                    status: FileImportStatus::FAILED,
                    importedOn: SerializableDateTime::fromString('2026-06-04 10:00:00'),
                    errorMessage: 'Could not parse file',
                    activityId: ActivityId::fromUnprefixed('42'),
                    activityName: 'Morning Run',
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

        $overview = $this->fileImportOverviewRepository->find($pagination);

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

    public function testFindReturnsAnEmptyOverviewWhenThereIsNoData(): void
    {
        $overview = $this->fileImportOverviewRepository->find(Pagination::fromOffsetAndLimit(0, 10));

        $this->assertTrue($overview->isEmpty());
        $this->assertSame([], $overview->getItems());
        $this->assertEquals(0, $overview->getTotal());
    }

    private function seedThreeFileImports(): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withOriginalFilename('oldest.fit')
                ->withImportedOn(SerializableDateTime::fromString('2026-06-01 08:00:00'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('2'))
                ->withOriginalFilename('middle.fit')
                ->withImportedOn(SerializableDateTime::fromString('2026-06-02 08:00:00'))
                ->build()
        );
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('3'))
                ->withOriginalFilename('newest.fit')
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
            $this->getConnection()
        );
        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection()
        );
    }
}
