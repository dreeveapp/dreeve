<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Infrastructure\Eventing\DomainEvent;

final class GearMaintenanceConfigWasUpdated extends DomainEvent
{
}
