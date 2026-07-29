<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Repository\Item;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ActivityOverviewItem implements Item
{
    private function __construct(
        private ActivityId $activityId,
        private ActivityName $name,
        private SportType $sportType,
        private SerializableDateTime $startDate,
        private ImportSource $importSource,
        private ?string $gearName,
        private ?string $deviceName,
        private bool $isCommute,
        private int $totalImageCount,
    ) {
    }

    public static function fromState(
        ActivityId $activityId,
        ActivityName $name,
        SportType $sportType,
        SerializableDateTime $startDate,
        ImportSource $importSource,
        ?string $gearName,
        ?string $deviceName,
        bool $isCommute,
        int $totalImageCount,
    ): self {
        return new self(
            activityId: $activityId,
            name: $name,
            sportType: $sportType,
            startDate: $startDate,
            importSource: $importSource,
            gearName: $gearName,
            deviceName: $deviceName,
            isCommute: $isCommute,
            totalImageCount: $totalImageCount,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getName(): ActivityName
    {
        return $this->name;
    }

    public function getSportType(): SportType
    {
        return $this->sportType;
    }

    public function getStartDate(): SerializableDateTime
    {
        return $this->startDate;
    }

    public function getImportSource(): ImportSource
    {
        return $this->importSource;
    }

    public function getGearName(): ?string
    {
        return $this->gearName;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function isCommute(): bool
    {
        return $this->isCommute;
    }

    public function getTotalImageCount(): int
    {
        return $this->totalImageCount;
    }
}
