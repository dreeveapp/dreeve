<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

final readonly class GearShift
{
    private function __construct(
        private int $timeOffsetInSeconds,
        private ?int $frontGearNumber,
        private ?int $frontGearTeeth,
        private ?int $rearGearNumber,
        private ?int $rearGearTeeth,
    ) {
    }

    public static function create(
        int $timeOffsetInSeconds,
        ?int $frontGearNumber,
        ?int $frontGearTeeth,
        ?int $rearGearNumber,
        ?int $rearGearTeeth,
    ): self {
        return new self(
            timeOffsetInSeconds: $timeOffsetInSeconds,
            frontGearNumber: $frontGearNumber,
            frontGearTeeth: $frontGearTeeth,
            rearGearNumber: $rearGearNumber,
            rearGearTeeth: $rearGearTeeth,
        );
    }

    public function getTimeOffsetInSeconds(): int
    {
        return $this->timeOffsetInSeconds;
    }

    public function getFrontGearNumber(): ?int
    {
        return $this->frontGearNumber;
    }

    public function getFrontGearTeeth(): ?int
    {
        return $this->frontGearTeeth;
    }

    public function getRearGearNumber(): ?int
    {
        return $this->rearGearNumber;
    }

    public function getRearGearTeeth(): ?int
    {
        return $this->rearGearTeeth;
    }

    public function withFrontGear(?int $frontGearNumber, ?int $frontGearTeeth): self
    {
        return new self(
            timeOffsetInSeconds: $this->timeOffsetInSeconds,
            frontGearNumber: $frontGearNumber,
            frontGearTeeth: $frontGearTeeth,
            rearGearNumber: $this->rearGearNumber,
            rearGearTeeth: $this->rearGearTeeth,
        );
    }
}
