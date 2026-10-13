<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\GearComponentId;

final readonly class InstallationParent implements \JsonSerializable, \Stringable
{
    private function __construct(
        private InstallationParentType $type,
        private GearId|GearComponentId $id,
    ) {
    }

    public static function gear(GearId $gearId): self
    {
        return new self(
            type: InstallationParentType::GEAR,
            id: $gearId,
        );
    }

    public static function component(GearComponentId $gearComponentId): self
    {
        return new self(
            type: InstallationParentType::COMPONENT,
            id: $gearComponentId,
        );
    }

    public static function fromString(string $parent): self
    {
        [$type, $id] = array_pad(explode(':', $parent, 2), 2, '');
        if ('' === trim($id)) {
            throw new \InvalidArgumentException(sprintf('Invalid installation parent "%s"', $parent));
        }

        return match (InstallationParentType::tryFrom($type)) {
            InstallationParentType::GEAR => self::gear(GearId::fromString(trim($id))),
            InstallationParentType::COMPONENT => self::component(GearComponentId::fromString(trim($id))),
            default => throw new \InvalidArgumentException(sprintf('Invalid installation parent "%s"', $parent)),
        };
    }

    public function getGearId(): ?GearId
    {
        return $this->id instanceof GearId ? $this->id : null;
    }

    public function getGearComponentId(): ?GearComponentId
    {
        return $this->id instanceof GearComponentId ? $this->id : null;
    }

    public function __toString(): string
    {
        return $this->type->value.':'.$this->id;
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
