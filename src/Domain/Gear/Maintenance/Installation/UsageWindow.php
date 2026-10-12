<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UsageWindow
{
    private function __construct(
        private GearId $gearId,
        private ?SerializableDateTime $from,
        private ?SerializableDateTime $until,
    ) {
    }

    public static function create(
        GearId $gearId,
        ?SerializableDateTime $from,
        ?SerializableDateTime $until,
    ): self {
        return new self(
            gearId: $gearId,
            from: $from,
            until: $until,
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
}
