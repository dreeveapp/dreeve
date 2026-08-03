<?php

declare(strict_types=1);

namespace App\Domain\Challenge;

use App\Infrastructure\Eventing\DomainEvent;

final class ChallengeWasImported extends DomainEvent
{
}
