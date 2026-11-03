<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Log\AddGearMaintenanceLog;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Log\AddGearMaintenanceLog\AddGearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class AddGearMaintenanceLogCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private GearMaintenanceLogRepository $gearMaintenanceLogRepository;

    public function testHandle(): void
    {
        $this->commandBus->dispatch(AddGearMaintenanceLog::fromPayload([
            'gearId' => (string) GearId::fromUnprefixed('b1'),
            'maintenanceTaskId' => (string) MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            'performedOn' => '2025-01-01 00:00:00',
        ]));

        $logs = $this->gearMaintenanceLogRepository->findAll();
        $this->assertCount(1, $logs);

        $log = $logs->getFirst();
        $this->assertNotNull($log);
        $this->assertEquals(GearId::fromUnprefixed('b1'), $log->getGearId());
        $this->assertEquals(MaintenanceTaskId::fromUnprefixed('chain-lubed'), $log->getMaintenanceTaskId());
        $this->assertEquals(SerializableDateTime::fromString('2025-01-01 00:00:00'), $log->getPerformedOn());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->gearMaintenanceLogRepository = $this->getContainer()->get(GearMaintenanceLogRepository::class);
    }
}
