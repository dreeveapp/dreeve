<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

interface GearComponentRepository
{
    public function add(GearComponent $gearComponent): void;

    public function update(GearComponent $gearComponent): void;

    public function delete(GearComponentId $gearComponentId): void;

    public function find(GearComponentId $gearComponentId): GearComponent;

    public function findAll(): GearComponents;
}
