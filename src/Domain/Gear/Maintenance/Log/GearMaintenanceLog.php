<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Eventing\RecordsEvents;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'GearMaintenanceLog')]
#[ORM\Index(name: 'GearMaintenanceLog_gearIndex', columns: ['gearId'])]
#[ORM\Index(name: 'GearMaintenanceLog_maintenanceTask', columns: ['maintenanceTaskId', 'performedOn'])]
final class GearMaintenanceLog
{
    use RecordsEvents;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private readonly GearMaintenanceLogId $gearMaintenanceLogId,
        #[ORM\Column(type: 'string')]
        private readonly GearId $gearId,
        #[ORM\Column(type: 'string')]
        private readonly MaintenanceTaskId $maintenanceTaskId,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly SerializableDateTime $performedOn,
    ) {
    }

    public static function create(
        GearId $gearId,
        MaintenanceTaskId $maintenanceTaskId,
        SerializableDateTime $performedOn,
    ): self {
        $gearMaintenanceLog = new self(
            gearMaintenanceLogId: GearMaintenanceLogId::random(),
            gearId: $gearId,
            maintenanceTaskId: $maintenanceTaskId,
            performedOn: $performedOn,
        );
        $gearMaintenanceLog->recordThat(new GearMaintenanceLogWasUpdated());

        return $gearMaintenanceLog;
    }

    public static function fromState(
        GearMaintenanceLogId $gearMaintenanceLogId,
        GearId $gearId,
        MaintenanceTaskId $maintenanceTaskId,
        SerializableDateTime $performedOn,
    ): self {
        return new self(
            gearMaintenanceLogId: $gearMaintenanceLogId,
            gearId: $gearId,
            maintenanceTaskId: $maintenanceTaskId,
            performedOn: $performedOn,
        );
    }

    public function withPerformedOn(SerializableDateTime $performedOn): self
    {
        $clone = new self(
            gearMaintenanceLogId: $this->gearMaintenanceLogId,
            gearId: $this->gearId,
            maintenanceTaskId: $this->maintenanceTaskId,
            performedOn: $performedOn,
        );
        if ($performedOn->getTimestamp() !== $this->performedOn->getTimestamp()) {
            $clone->recordThat(new GearMaintenanceLogWasUpdated());
        }

        return $clone;
    }

    public function getId(): GearMaintenanceLogId
    {
        return $this->gearMaintenanceLogId;
    }

    public function getGearId(): GearId
    {
        return $this->gearId;
    }

    public function getMaintenanceTaskId(): MaintenanceTaskId
    {
        return $this->maintenanceTaskId;
    }

    public function getPerformedOn(): SerializableDateTime
    {
        return $this->performedOn;
    }
}
