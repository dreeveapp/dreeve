<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

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

    public function isActiveOn(SerializableDateTime $on): bool
    {
        if ($this->from && $on->isBefore($this->from)) {
            return false;
        }

        return !$this->until || $on->isBefore($this->until);
    }
}
