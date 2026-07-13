<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Log;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Log\DbalGearMaintenanceLogOverviewRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogOverviewItem;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogOverviewRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use PHPUnit\Framework\Attributes\DataProvider;

class DbalGearMaintenanceLogOverviewRepositoryTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private GearMaintenanceLogOverviewRepository $gearMaintenanceLogOverviewRepository;
    private GearMaintenanceLogRepository $gearMaintenanceLogRepository;
    private GearRepository $gearRepository;

    public function testFindMapsRowToOverviewItemWithResolvedLabels(): void
    {
        $this->seedConfigAndGear();

        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $overview = $this->gearMaintenanceLogOverviewRepository->find(Pagination::fromOffsetAndLimit(0, 10));

        $this->assertEquals(
            [
                GearMaintenanceLogOverviewItem::fromState(
                    gearMaintenanceLogId: $log->getId(),
                    gearName: 'Race bike',
                    componentLabel: 'Some cool chain',
                    taskLabel: 'Lube',
                    performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
                ),
            ],
            $overview->getItems()
        );
        $this->assertEquals(1, $overview->getTotal());
    }

    public function testFindSkipsOrphanedLogsButKeepsValidOnes(): void
    {
        $this->seedConfigAndGear();

        $validLog = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($validLog);

        // Orphaned: the gear (g999) has no Gear row anymore.
        $this->gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g999'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-02-01 00:00:00'),
        ));

        // Orphaned: the maintenance task (chain-removed) is no longer in the config.
        $this->gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-removed'),
            performedOn: SerializableDateTime::fromString('2025-03-01 00:00:00'),
        ));

        $overview = $this->gearMaintenanceLogOverviewRepository->find(Pagination::fromOffsetAndLimit(0, 10));

        $this->assertEquals(
            [
                GearMaintenanceLogOverviewItem::fromState(
                    gearMaintenanceLogId: $validLog->getId(),
                    gearName: 'Race bike',
                    componentLabel: 'Some cool chain',
                    taskLabel: 'Lube',
                    performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
                ),
            ],
            $overview->getItems()
        );
        $this->assertEquals(1, $overview->getTotal());
    }

    public function testFindReturnsAnEmptyOverviewWhenThereIsNoMaintenanceConfig(): void
    {
        // No config imported: every existing log is orphaned.
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->withName('Race bike')
                ->build()
        );
        $this->gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));

        $overview = $this->gearMaintenanceLogOverviewRepository->find(Pagination::fromOffsetAndLimit(0, 10));

        $this->assertTrue($overview->isEmpty());
        $this->assertEquals(0, $overview->getTotal());
    }

    #[DataProvider('providePaginationScenarios')]
    public function testFindOrdersByPerformedOnDescAndPaginates(
        Pagination $pagination,
        array $expectedDates,
        int $expectedTotal,
    ): void {
        $this->seedConfigAndGear();
        $this->seedThreeLogs();

        $overview = $this->gearMaintenanceLogOverviewRepository->find($pagination);

        $this->assertSame(
            $expectedDates,
            array_map(
                static fn (GearMaintenanceLogOverviewItem $item): string => $item->getPerformedOn()->format('Y-m-d'),
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
            ['2025-03-01', '2025-02-01'],
            3,
        ];

        yield 'second page returns the remainder while total stays the same' => [
            Pagination::fromOffsetAndLimit(2, 2),
            ['2025-01-01'],
            3,
        ];

        yield 'a single page can hold everything' => [
            Pagination::fromOffsetAndLimit(0, 10),
            ['2025-03-01', '2025-02-01', '2025-01-01'],
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
        $this->seedConfigAndGear();

        $overview = $this->gearMaintenanceLogOverviewRepository->find(Pagination::fromOffsetAndLimit(0, 10));

        $this->assertTrue($overview->isEmpty());
        $this->assertSame([], $overview->getItems());
        $this->assertEquals(0, $overview->getTotal());
    }

    public function testFindOneByGearMaintenanceLogIdReturnsTheItemWithResolvedLabels(): void
    {
        $this->seedConfigAndGear();

        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $this->assertEquals(
            GearMaintenanceLogOverviewItem::fromState(
                gearMaintenanceLogId: $log->getId(),
                gearName: 'Race bike',
                componentLabel: 'Some cool chain',
                taskLabel: 'Lube',
                performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
            ),
            $this->gearMaintenanceLogOverviewRepository->findOneByGearMaintenanceLogId($log->getId())
        );
    }

    public function testFindOneByGearMaintenanceLogIdThrowsWhenUnknown(): void
    {
        $this->seedConfigAndGear();

        $gearMaintenanceLogId = GearMaintenanceLogId::random();
        $this->expectExceptionObject(new EntityNotFound(sprintf('Gear maintenance log "%s" is no longer available', $gearMaintenanceLogId)));
        $this->gearMaintenanceLogOverviewRepository->findOneByGearMaintenanceLogId($gearMaintenanceLogId);
    }

    public function testFindOneByGearMaintenanceLogIdThrowsWhenGearIsAbsent(): void
    {
        $this->seedConfigAndGear();

        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g999'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $this->expectExceptionObject(new EntityNotFound(sprintf('Gear maintenance log "%s" is no longer available', $log->getId())));
        $this->gearMaintenanceLogOverviewRepository->findOneByGearMaintenanceLogId($log->getId());
    }

    public function testFindOneByGearMaintenanceLogIdThrowsWhenTaskIsNotInConfig(): void
    {
        $this->seedConfigAndGear();

        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-removed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $this->expectExceptionObject(new EntityNotFound(sprintf('Gear maintenance log "%s" is no longer available', $log->getId())));
        $this->gearMaintenanceLogOverviewRepository->findOneByGearMaintenanceLogId($log->getId());
    }

    private function seedConfigAndGear(): void
    {
        $this->importGearMaintenanceConfig();
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->withName('Race bike')
                ->build()
        );
    }

    private function seedThreeLogs(): void
    {
        foreach (['2025-01-01', '2025-02-01', '2025-03-01'] as $date) {
            $this->gearMaintenanceLogRepository->add(
                GearMaintenanceLog::create(
                    gearId: GearId::fromUnprefixed('g10130856'),
                    maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                    performedOn: SerializableDateTime::fromString($date.' 00:00:00'),
                )
            );
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearRepository = $this->getContainer()->get(GearRepository::class);
        $this->gearMaintenanceLogRepository = $this->getContainer()->get(GearMaintenanceLogRepository::class);
        $this->gearMaintenanceLogOverviewRepository = new DbalGearMaintenanceLogOverviewRepository(
            $this->getConnection(),
            $this->getContainer()->get(GearMaintenanceRepository::class),
        );
    }
}
