<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityReference;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Image\NewImage;
use App\Domain\Image\ProvideLocalImageFromDropZonePayload;
use App\Domain\Image\RemovedImage;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UpdateActivityCollection extends DomainCommand implements DeserializableCommand
{
    use ProvideLocalImageFromDropZonePayload;
    use ProvidesCommandName;

    private function __construct(
        private ActivityCollectionId $activityCollectionId,
        private string $name,
        private ?string $description,
        private ActivityCollectionType $type,
        private ?NewImage $newImage,
        private ?RemovedImage $removedImage,
        private bool $useRouteMapAsCover,
        private ActivityIds $activityIds,
        private ?ActivityId $activityIdToAdd,
        private ?DateRange $addActivitiesInRange,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['activityCollectionId'], $payload['name'])
            || !is_string($payload['activityCollectionId'])
            || !is_string($payload['name'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "activityCollectionId" and "name" are required.');
        }

        $name = trim($payload['name']);
        if ('' === $name) {
            throw CouldNotDeserializeCommand::invalidPayload('The name cannot be empty.');
        }

        $type = $payload['type'] ?? null;
        if (!is_string($type) || !$activityCollectionType = ActivityCollectionType::tryFrom($type)) {
            throw CouldNotDeserializeCommand::invalidPayload('The type is invalid.');
        }

        $description = $payload['description'] ?? null;
        $description = is_string($description) ? trim($description) : '';

        $activityIds = $payload['activityIds'] ?? [];
        if (!is_array($activityIds)) {
            throw CouldNotDeserializeCommand::invalidPayload('The "activityIds" must be a list.');
        }

        try {
            $activityIds = ActivityIds::fromArray(array_map(
                ActivityId::fromString(...),
                array_values($activityIds),
            ))->unique();
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The "activityIds" contain an invalid activity id.');
        }

        $activityIdToAdd = $payload['activityIdToAdd'] ?? '';
        $addActivitiesFrom = $payload['addActivitiesFrom'] ?? '';
        $addActivitiesTill = $payload['addActivitiesTill'] ?? '';
        $addsDateRange = '' !== $addActivitiesFrom || '' !== $addActivitiesTill;
        if ('' !== $activityIdToAdd && $addsDateRange) {
            throw CouldNotDeserializeCommand::invalidPayload('Add one activity or a date range, not both.');
        }

        try {
            $activityIdToAdd = '' === $activityIdToAdd ? null : ActivityReference::parse($activityIdToAdd);
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The "activityIdToAdd" is an invalid activity id.');
        }

        $from = is_string($addActivitiesFrom) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $addActivitiesFrom) : false;
        $till = is_string($addActivitiesTill) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $addActivitiesTill) : false;
        if ($addsDateRange && (!$from || $from->format('Y-m-d') !== $addActivitiesFrom || !$till || $till->format('Y-m-d') !== $addActivitiesTill)) {
            throw CouldNotDeserializeCommand::invalidPayload('Fill in both dates to add the activities in a date range.');
        }

        try {
            $addActivitiesInRange = $from && $till ? DateRange::fromDates(
                from: SerializableDateTime::fromDateTimeImmutable($from),
                till: SerializableDateTime::fromDateTimeImmutable($till),
            ) : null;
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The start date must be on or before the end date.');
        }

        [$newImages, $removedImages] = self::parseImages($payload, 'coverImagePath');

        return new self(
            activityCollectionId: ActivityCollectionId::fromString($payload['activityCollectionId']),
            name: $name,
            description: '' === $description ? null : $description,
            type: $activityCollectionType,
            newImage: $newImages[0] ?? null,
            removedImage: $removedImages[0] ?? null,
            useRouteMapAsCover: filter_var($payload['useRouteMapAsCover'] ?? false, FILTER_VALIDATE_BOOLEAN),
            activityIds: $activityIds,
            activityIdToAdd: $activityIdToAdd,
            addActivitiesInRange: $addActivitiesInRange,
        );
    }

    public function getActivityCollectionId(): ActivityCollectionId
    {
        return $this->activityCollectionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): ActivityCollectionType
    {
        return $this->type;
    }

    public function getNewImage(): ?NewImage
    {
        return $this->newImage;
    }

    public function getRemovedImage(): ?RemovedImage
    {
        return $this->removedImage;
    }

    public function useRouteMapAsCover(): bool
    {
        return $this->useRouteMapAsCover;
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }

    public function getActivityIdToAdd(): ?ActivityId
    {
        return $this->activityIdToAdd;
    }

    public function getAddActivitiesInRange(): ?DateRange
    {
        return $this->addActivitiesInRange;
    }
}
