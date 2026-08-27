<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<GearShift>
 */
final class GearShifts extends Collection
{
    public function getItemClassName(): string
    {
        return GearShift::class;
    }
}
