<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\GearComponentId;

final readonly class InstallationParent implements \JsonSerializable
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

    public function getType(): InstallationParentType
    {
        return $this->type;
    }

    public function getGearId(): ?GearId
    {
        return $this->id instanceof GearId ? $this->id : null;
    }

    public function getGearComponentId(): ?GearComponentId
    {
        return $this->id instanceof GearComponentId ? $this->id : null;
    }

    /**
     * @return array{type: string, id: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id instanceof GearId ? $this->id->toUnprefixedString() : (string) $this->id,
        ];
    }
}
