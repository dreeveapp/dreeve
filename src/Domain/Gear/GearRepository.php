<?php

declare(strict_types=1);

namespace App\Domain\Gear;

interface GearRepository
{
    public function add(Gear $gear): void;

    public function update(Gear $gear): void;

    public function findAll(): Gears;

    public function findAllUsed(): Gears;

    public function find(GearId $gearId): Gear;

    public function hasGear(): bool;
}
