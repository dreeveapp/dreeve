<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UsageWindow
{
    private function __construct(
        private GearId $gearId,
        private ?SerializableDateTime $from,
        private ?SerializableDateTime $until,
        private SportTypes $sportTypes,
    ) {
    }

    public static function create(
        GearId $gearId,
        ?SerializableDateTime $from,
        ?SerializableDateTime $until,
        SportTypes $sportTypes,
    ): self {
        return new self(
            gearId: $gearId,
            from: $from,
            until: $until,
            sportTypes: $sportTypes,
        );
    }

    public function getGearId(): GearId
    {
        return $this->gearId;
    }

    public function getFrom(): ?SerializableDateTime
    {
        return $this->from;
    }

    public function getUntil(): ?SerializableDateTime
    {
        return $this->until;
    }

    public function getSportTypes(): SportTypes
    {
        return $this->sportTypes;
    }

    public function narrowedBy(GearComponentInstallation $installation): ?self
    {
        $from = $installation->getFrom();
        if (!$from instanceof SerializableDateTime || ($this->from instanceof SerializableDateTime && $this->from->isAfter($from))) {
            $from = $this->from;
        }
        $until = $installation->getUntil();
        if (!$until instanceof SerializableDateTime || ($this->until instanceof SerializableDateTime && $this->until->isBefore($until))) {
            $until = $this->until;
        }
        if ($from instanceof SerializableDateTime && $until instanceof SerializableDateTime && $until->isBeforeOrOn($from)) {
            return null;
        }

        $sportTypes = $installation->getSportTypes();
        if ($sportTypes->isEmpty()) {
            $sportTypes = $this->sportTypes;
        } elseif (!$this->sportTypes->isEmpty()) {
            $sportTypes = $sportTypes->filter(fn (SportType $sportType): bool => $this->sportTypes->has($sportType));
            if ($sportTypes->isEmpty()) {
                return null;
            }
        }

        return new self(
            gearId: $this->gearId,
            from: $from,
            until: $until,
            sportTypes: $sportTypes,
        );
    }

    public function isActiveOn(SerializableDateTime $on): bool
    {
        if ($this->from instanceof SerializableDateTime && $on->isBefore($this->from)) {
            return false;
        }

        return !$this->until instanceof SerializableDateTime || $on->isBefore($this->until);
    }
}
