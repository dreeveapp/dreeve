<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Log\DeleteGearMaintenanceLog;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Log\DeleteGearMaintenanceLog\DeleteGearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogs;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class DeleteGearMaintenanceLogCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private GearMaintenanceLogRepository $gearMaintenanceLogRepository;

    public function testHandle(): void
    {
        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        $this->gearMaintenanceLogRepository->add($log);

        $this->commandBus->dispatch(DeleteGearMaintenanceLog::fromPayload([
            'gearMaintenanceLogId' => (string) $log->getId(),
        ]));

        $this->assertEquals(
            GearMaintenanceLogs::empty(),
            $this->gearMaintenanceLogRepository->findAll(),
        );

        $this->commandBus->dispatch(DeleteGearMaintenanceLog::fromPayload([
            'gearMaintenanceLogId' => (string) $log->getId(),
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->gearMaintenanceLogRepository = $this->getContainer()->get(GearMaintenanceLogRepository::class);
    }
}
