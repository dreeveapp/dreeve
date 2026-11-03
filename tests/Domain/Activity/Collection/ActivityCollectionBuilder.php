<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection;

use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class ActivityCollectionBuilder
{
    private ActivityCollectionId $activityCollectionId;
    private string $name = 'Mallorca';
    private ?string $description = null;
    private ActivityCollectionType $type = ActivityCollectionType::TRIP;
    private ActivityIds $activityIds;
    private SerializableDateTime $createdOn;

    private function __construct()
    {
        $this->activityCollectionId = ActivityCollectionId::fromUnprefixed('1');
        $this->activityIds = ActivityIds::empty();
        $this->createdOn = SerializableDateTime::fromString('2025-04-20 10:00:00');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ActivityCollection
    {
        return ActivityCollection::create(
            activityCollectionId: $this->activityCollectionId,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            activityIds: $this->activityIds,
            createdOn: $this->createdOn,
        );
    }

    public function withActivityCollectionId(ActivityCollectionId $activityCollectionId): self
    {
        $this->activityCollectionId = $activityCollectionId;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function withDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function withType(ActivityCollectionType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function withActivityIds(ActivityIds $activityIds): self
    {
        $this->activityIds = $activityIds;

        return $this;
    }

    public function withCreatedOn(SerializableDateTime $createdOn): self
    {
        $this->createdOn = $createdOn;

        return $this;
    }
}
