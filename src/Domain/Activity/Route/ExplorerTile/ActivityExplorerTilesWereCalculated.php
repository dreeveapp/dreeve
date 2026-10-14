<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Infrastructure\Eventing\DomainEvent;

final class ActivityExplorerTilesWereCalculated extends DomainEvent
{
}
