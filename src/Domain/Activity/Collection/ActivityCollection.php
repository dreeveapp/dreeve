<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Eventing\RecordsEvents;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityCollection')]
final class ActivityCollection
{
    use RecordsEvents;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private readonly ActivityCollectionId $activityCollectionId,
        #[ORM\Column(type: 'string')]
        private readonly string $name,
        #[ORM\Column(type: 'text', nullable: true)]
        private readonly ?string $description,
        #[ORM\Column(type: 'string', enumType: ActivityCollectionType::class)]
        private readonly ActivityCollectionType $type,
        #[ORM\Column(type: 'json')]
        private readonly ActivityIds $activityIds,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?string $coverImagePath,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly SerializableDateTime $createdOn,
    ) {
    }

    public static function create(
        ActivityCollectionId $activityCollectionId,
        string $name,
        ?string $description,
        ActivityCollectionType $type,
        ActivityIds $activityIds,
        ?string $coverImagePath,
        SerializableDateTime $createdOn,
    ): self {
        $activityCollection = new self(
            activityCollectionId: $activityCollectionId,
            name: $name,
            description: $description,
            type: $type,
            activityIds: $activityIds,
            coverImagePath: $coverImagePath,
            createdOn: $createdOn,
        );
        $activityCollection->recordThat(new ActivityCollectionWasAdded());

        return $activityCollection;
    }

    public static function fromState(
        ActivityCollectionId $activityCollectionId,
        string $name,
        ?string $description,
        ActivityCollectionType $type,
        ActivityIds $activityIds,
        ?string $coverImagePath,
        SerializableDateTime $createdOn,
    ): self {
        return new self(
            activityCollectionId: $activityCollectionId,
            name: $name,
            description: $description,
            type: $type,
            activityIds: $activityIds,
            coverImagePath: $coverImagePath,
            createdOn: $createdOn,
        );
    }

    public function getId(): ActivityCollectionId
    {
        return $this->activityCollectionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        if ($name === $this->name) {
            return $this;
        }

        $activityCollection = clone ($this, [
            'name' => $name,
        ]);
        $activityCollection->recordOnlyOnce(new ActivityCollectionWasUpdated($this->activityCollectionId));

        return $activityCollection;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function withDescription(?string $description): self
    {
        if ($description === $this->description) {
            return $this;
        }

        $activityCollection = clone ($this, [
            'description' => $description,
        ]);
        $activityCollection->recordOnlyOnce(new ActivityCollectionWasUpdated($this->activityCollectionId));

        return $activityCollection;
    }

    public function getType(): ActivityCollectionType
    {
        return $this->type;
    }

    public function withType(ActivityCollectionType $type): self
    {
        if ($type === $this->type) {
            return $this;
        }

        $activityCollection = clone ($this, [
            'type' => $type,
        ]);
        $activityCollection->recordOnlyOnce(new ActivityCollectionWasUpdated($this->activityCollectionId));

        return $activityCollection;
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }

    public function withActivityIds(ActivityIds $activityIds): self
    {
        if (count($activityIds) === count($this->activityIds)
            && !array_any($activityIds->toArray(), fn (ActivityId $activityId): bool => !$this->activityIds->has($activityId))) {
            return $this;
        }

        $activityCollection = clone ($this, [
            'activityIds' => $activityIds,
        ]);
        $activityCollection->recordOnlyOnce(new ActivityCollectionWasUpdated($this->activityCollectionId));

        return $activityCollection;
    }

    public function getCoverImagePath(): ?string
    {
        if (null === $this->coverImagePath) {
            return null;
        }

        return str_starts_with($this->coverImagePath, '/') ? $this->coverImagePath : '/'.$this->coverImagePath;
    }

    public function withCoverImagePath(?string $coverImagePath): self
    {
        if ($coverImagePath === $this->coverImagePath) {
            return $this;
        }

        $activityCollection = clone ($this, [
            'coverImagePath' => $coverImagePath,
        ]);
        $activityCollection->recordOnlyOnce(new ActivityCollectionWasUpdated($this->activityCollectionId));

        return $activityCollection;
    }

    public function delete(): void
    {
        $this->recordThat(new ActivityCollectionWasDeleted($this->activityCollectionId));
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return $this->createdOn;
    }
}
