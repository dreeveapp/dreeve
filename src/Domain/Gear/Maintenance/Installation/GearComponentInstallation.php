<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class GearComponentInstallation implements \JsonSerializable
{
    private function __construct(
        private InstallationParent $parent,
        private ?SerializableDateTime $from,
        private ?SerializableDateTime $until,
        private SportTypes $sportTypes,
    ) {
        if ($this->from && $this->until && $this->until->isBeforeOrOn($this->from)) {
            throw new \InvalidArgumentException('An installation must end after it starts');
        }
    }

    public static function create(
        InstallationParent $parent,
        ?SerializableDateTime $from,
        ?SerializableDateTime $until,
        SportTypes $sportTypes,
    ): self {
        return new self(
            parent: $parent,
            from: $from,
            until: $until,
            sportTypes: $sportTypes,
        );
    }

    public function getParent(): InstallationParent
    {
        return $this->parent;
    }

    public function getFrom(): ?SerializableDateTime
    {
        return $this->from;
    }

    public function getUntil(): ?SerializableDateTime
    {
        return $this->until;
    }

    public function getSportTypes(): SportTypes
    {
        return $this->sportTypes;
    }

    public function isActiveOn(SerializableDateTime $on): bool
    {
        if ($this->from && $on->isBefore($this->from)) {
            return false;
        }

        return !$this->until || $on->isBefore($this->until);
    }

    /**
     * @return array{parent: InstallationParent, from: ?string, until: ?string, sportTypes: array<mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'parent' => $this->parent,
            'from' => $this->from?->format('Y-m-d'),
            'until' => $this->until?->format('Y-m-d'),
            'sportTypes' => $this->sportTypes->map(static fn (SportType $sportType): string => $sportType->value),
        ];
    }
}
