<?php

namespace App\Tests\Domain\Gear\Maintenance\Log;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Log\DbalGearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogs;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class DbalGearMaintenanceLogRepositoryTest extends ContainerTestCase
{
    private GearMaintenanceLogRepository $gearMaintenanceLogRepository;

    public function testAddAndFindAll(): void
    {
        $older = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $newer = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-03-01 00:00:00'),
        );

        $this->gearMaintenanceLogRepository->add($older);
        $this->gearMaintenanceLogRepository->add($newer);

        // findAll orders by performedOn DESC.
        $this->assertEquals(
            GearMaintenanceLogs::fromArray([$newer, $older]),
            $this->gearMaintenanceLogRepository->findAll(),
        );
    }

    public function testFindAllWithoutLogs(): void
    {
        $this->assertEquals(
            GearMaintenanceLogs::empty(),
            $this->gearMaintenanceLogRepository->findAll(),
        );
    }

    public function testFind(): void
    {
        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $this->assertEquals(
            $log,
            $this->gearMaintenanceLogRepository->find($log->getId()),
        );
    }

    public function testFindItShouldThrowWhenNotFound(): void
    {
        $gearMaintenanceLogId = GearMaintenanceLogId::random();
        $this->expectExceptionObject(new EntityNotFound(sprintf('GearMaintenanceLog "%s" not found', $gearMaintenanceLogId)));

        $this->gearMaintenanceLogRepository->find($gearMaintenanceLogId);
    }

    public function testUpdate(): void
    {
        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $updated = $log->withPerformedOn(SerializableDateTime::fromString('2025-06-01 00:00:00'));
        $this->gearMaintenanceLogRepository->update($updated);

        $this->assertEquals(
            $updated,
            $this->gearMaintenanceLogRepository->find($log->getId()),
        );
    }

    public function testDelete(): void
    {
        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $this->gearMaintenanceLogRepository->delete($log->getId());

        $this->assertEquals(
            GearMaintenanceLogs::empty(),
            $this->gearMaintenanceLogRepository->findAll(),
        );
    }

    public function testFindMostRecentForMaintenanceTask(): void
    {
        $older = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $newer = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-03-01 00:00:00'),
        );
        $otherTask = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-replaced'),
            performedOn: SerializableDateTime::fromString('2025-06-01 00:00:00'),
        );

        $this->gearMaintenanceLogRepository->add($older);
        $this->gearMaintenanceLogRepository->add($newer);
        $this->gearMaintenanceLogRepository->add($otherTask);

        $this->assertEquals(
            $newer,
            $this->gearMaintenanceLogRepository->findMostRecentForMaintenanceTask(MaintenanceTaskId::fromUnprefixed('chain-lubed')),
        );
    }

    public function testFindMostRecentForMaintenanceTaskReturnsNullWhenNeverMaintained(): void
    {
        $this->assertNull(
            $this->gearMaintenanceLogRepository->findMostRecentForMaintenanceTask(MaintenanceTaskId::fromUnprefixed('chain-lubed')),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearMaintenanceLogRepository = new DbalGearMaintenanceLogRepository(
            $this->getConnection(),
        );
    }
}
