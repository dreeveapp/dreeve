<?php

declare(strict_types=1);

namespace App\Domain\Activity\ManuallyCreateActivity;

use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ProvideCaloriesFromPayload;
use App\Domain\Activity\ProvideManuallyAddedActivityPayload;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Gear\GearId;
use App\Domain\Image\NewImage;
use App\Domain\Image\ProvideLocalImageFromDropZonePayload;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ManuallyCreateActivity extends DomainCommand implements DeserializableCommand
{
    use ProvideCaloriesFromPayload;
    use ProvideLocalImageFromDropZonePayload;
    use ProvideManuallyAddedActivityPayload;
    use ProvidesCommandName;

    /**
     * @param array<NewImage> $newImages
     */
    private function __construct(
        private ActivityName $name,
        private ?string $description,
        private SportType $sportType,
        private ?WorkoutType $workoutType,
        private SerializableDateTime $startDateTime,
        private int $durationInSeconds,
        private float $distance,
        private float $elevation,
        private ?GearId $gearId,
        private ?int $calories,
        private bool $isCommute,
        private bool $isGroupActivity,
        private array $newImages,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        [$newImages] = self::parseImages($payload, 'images');

        return new self(
            name: self::parseName($payload),
            description: self::parseOptionalString($payload, 'description'),
            sportType: self::parseSportType($payload),
            workoutType: self::parseWorkoutType($payload),
            startDateTime: self::parseStartDateTime($payload),
            durationInSeconds: self::parseDurationInSeconds($payload),
            distance: self::parsePositiveNumber($payload, 'distance'),
            elevation: self::parsePositiveNumber($payload, 'elevation'),
            gearId: self::parseGearId($payload),
            calories: self::parseCalories($payload),
            isCommute: self::parseIsCommute($payload),
            isGroupActivity: self::parseIsGroupActivity($payload),
            newImages: $newImages,
        );
    }

    public function getName(): ActivityName
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSportType(): SportType
    {
        return $this->sportType;
    }

    public function getWorkoutType(): ?WorkoutType
    {
        return $this->workoutType;
    }

    public function getStartDateTime(): SerializableDateTime
    {
        return $this->startDateTime;
    }

    public function getDurationInSeconds(): int
    {
        return $this->durationInSeconds;
    }

    public function getDistance(): float
    {
        return $this->distance;
    }

    public function getElevation(): float
    {
        return $this->elevation;
    }

    public function getGearId(): ?GearId
    {
        return $this->gearId;
    }

    public function getCalories(): ?int
    {
        return $this->calories;
    }

    public function isCommute(): bool
    {
        return $this->isCommute;
    }

    public function isGroupActivity(): bool
    {
        return $this->isGroupActivity;
    }

    /**
     * @return array<NewImage>
     */
    public function getNewImages(): array
    {
        return $this->newImages;
    }
}
