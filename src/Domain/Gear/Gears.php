<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<Gear>
 */
final class Gears extends Collection
{
    public function getItemClassName(): string
    {
        return Gear::class;
    }

    public function sortByIsRetired(): self
    {
        return $this->usort(fn (Gear $a, Gear $b): int => $a->isRetired() <=> $b->isRetired());
    }

    public function sortByName(): self
    {
        return $this->usort(fn (Gear $a, Gear $b): int => strcasecmp($a->getOriginalName(), $b->getOriginalName()));
    }

    public function getByGearId(?GearId $gearId): ?Gear
    {
        if (is_null($gearId)) {
            return null;
        }
        $gears = $this->filter(fn (Gear $gear): bool => $gearId == $gear->getId())->toArray();

        return reset($gears) ?: null;
    }
}
