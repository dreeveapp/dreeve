<?php

declare(strict_types=1);

namespace App\Domain\Milestone;

use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class PreviousMilestone implements \JsonSerializable
{
    private function __construct(
        private MilestoneId $previousMilestoneId,
        private Unit $threshold,
        private SerializableDateTime $achievedOn,
    ) {
    }

    public static function create(
        MilestoneId $previousMilestoneId,
        Unit $threshold,
        SerializableDateTime $achievedOn,
    ): self {
        return new self(
            previousMilestoneId: $previousMilestoneId,
            threshold: $threshold,
            achievedOn: $achievedOn,
        );
    }

    public function getPreviousMilestoneId(): MilestoneId
    {
        return $this->previousMilestoneId;
    }

    public function getThreshold(): Unit
    {
        return $this->threshold;
    }

    public function getAchievedOn(): SerializableDateTime
    {
        return $this->achievedOn;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'previousMilestoneId' => $this->previousMilestoneId,
            'threshold' => $this->threshold,
            'achievedOn' => $this->achievedOn,
        ];
    }
}
