<?php

declare(strict_types=1);

namespace App\Domain\Activity\UpdateManuallyAddedActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ProvideManuallyAddedActivityPayload;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Gear\GearId;
use App\Domain\Image\NewImage;
use App\Domain\Image\ProvideLocalImageFromDropZonePayload;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\InvalidatesCacheTags;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

#[RequiresRebuild]
#[InvalidatesCacheTags(CacheTag::ACTIVITIES)]
final readonly class UpdateManuallyAddedActivity extends DomainCommand implements DeserializableCommand
{
    use ProvideLocalImageFromDropZonePayload;
    use ProvideManuallyAddedActivityPayload;
    use ProvidesCommandName;

    /**
     * @param array<NewImage>     $newImages
     * @param array<RemovedImage> $removedImages
     */
    private function __construct(
        private ActivityId $activityId,
        private ActivityName $name,
        private ?string $description,
        private SportType $sportType,
        private ?WorkoutType $workoutType,
        private SerializableDateTime $startDateTime,
        private int $durationInSeconds,
        private float $distance,
        private float $elevation,
        private ?GearId $gearId,
        private bool $isCommute,
        private array $newImages,
        private array $removedImages,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['activityId']) || !is_string($payload['activityId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "activityId" is required.');
        }

        [$newImages, $removedImages] = self::parseImages($payload, 'images');

        return new self(
            activityId: ActivityId::fromString($payload['activityId']),
            name: self::parseName($payload),
            description: self::parseOptionalString($payload, 'description'),
            sportType: self::parseSportType($payload),
            workoutType: self::parseWorkoutType($payload),
            startDateTime: self::parseStartDateTime($payload),
            durationInSeconds: self::parseDurationInSeconds($payload),
            distance: self::parsePositiveNumber($payload, 'distance'),
            elevation: self::parsePositiveNumber($payload, 'elevation'),
            gearId: self::parseGearId($payload),
            isCommute: self::parseIsCommute($payload),
            newImages: $newImages,
            removedImages: $removedImages,
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

    public function isCommute(): bool
    {
        return $this->isCommute;
    }

    /**
     * @return array<NewImage>
     */
    public function getNewImages(): array
    {
        return $this->newImages;
    }

    /**
     * @return array<RemovedImage>
     */
    public function getRemovedImages(): array
    {
        return $this->removedImages;
    }
}
