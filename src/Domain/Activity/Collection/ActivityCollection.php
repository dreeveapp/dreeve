<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\ActivityIds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityCollection')]
final class ActivityCollection
{
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
        SerializableDateTime $createdOn,
    ): self {
        return new self(
            activityCollectionId: $activityCollectionId,
            name: $name,
            description: $description,
            type: $type,
            activityIds: $activityIds,
            createdOn: $createdOn,
        );
    }

    public static function fromState(
        ActivityCollectionId $activityCollectionId,
        string $name,
        ?string $description,
        ActivityCollectionType $type,
        ActivityIds $activityIds,
        SerializableDateTime $createdOn,
    ): self {
        return new self(
            activityCollectionId: $activityCollectionId,
            name: $name,
            description: $description,
            type: $type,
            activityIds: $activityIds,
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
        return clone ($this, [
            'name' => $name,
        ]);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function withDescription(?string $description): self
    {
        return clone ($this, [
            'description' => $description,
        ]);
    }

    public function getType(): ActivityCollectionType
    {
        return $this->type;
    }

    public function withType(ActivityCollectionType $type): self
    {
        return clone ($this, [
            'type' => $type,
        ]);
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return $this->createdOn;
    }
}
