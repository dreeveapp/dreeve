<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Infrastructure\Eventing\DomainEvent;

final class GearWasUpdated extends DomainEvent
{
}
