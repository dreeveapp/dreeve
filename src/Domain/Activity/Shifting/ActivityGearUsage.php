<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityGearUsage')]
#[ORM\Index(name: 'ActivityGearUsage_positionTeeth', columns: ['position', 'teeth'])]
final readonly class ActivityGearUsage
{
    use ProvideTimeFormats;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Id, ORM\Column(type: 'string')]
        private GearPosition $position,
        #[ORM\Id, ORM\Column(type: 'integer')]
        private int $gearNumber,
        #[ORM\Column(type: 'integer')]
        private int $teeth,
        #[ORM\Column(type: 'integer')]
        private int $timeInSeconds,
        #[ORM\Column(type: 'integer')]
        private int $shiftCount,
    ) {
    }

    public static function create(
        ActivityId $activityId,
        GearPosition $position,
        int $gearNumber,
        int $teeth,
        int $timeInSeconds,
        int $shiftCount,
    ): self {
        return new self(
            activityId: $activityId,
            position: $position,
            gearNumber: $gearNumber,
            teeth: $teeth,
            timeInSeconds: $timeInSeconds,
            shiftCount: $shiftCount,
        );
    }

    public static function none(ActivityId $activityId): self
    {
        return new self(
            activityId: $activityId,
            position: GearPosition::NONE,
            gearNumber: 0,
            teeth: 0,
            timeInSeconds: 0,
            shiftCount: 0,
        );
    }

    public static function fromState(
        ActivityId $activityId,
        GearPosition $position,
        int $gearNumber,
        int $teeth,
        int $timeInSeconds,
        int $shiftCount,
    ): self {
        return new self(
            activityId: $activityId,
            position: $position,
            gearNumber: $gearNumber,
            teeth: $teeth,
            timeInSeconds: $timeInSeconds,
            shiftCount: $shiftCount,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getPosition(): GearPosition
    {
        return $this->position;
    }

    public function getGearNumber(): int
    {
        return $this->gearNumber;
    }

    public function getTeeth(): int
    {
        return $this->teeth;
    }

    public function getTimeInSeconds(): int
    {
        return $this->timeInSeconds;
    }

    public function getFormattedTime(): string
    {
        return $this->formatDurationAsPaddedClock($this->timeInSeconds);
    }

    public function getShiftCount(): int
    {
        return $this->shiftCount;
    }
}
