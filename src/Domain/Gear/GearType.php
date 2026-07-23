<?php

declare(strict_types=1);

namespace App\Domain\Gear;

enum GearType: string
{
    case IMPORTED = 'imported';
    case CUSTOM = 'custom';

    public function isImported(): bool
    {
        return self::IMPORTED === $this;
    }

    public function isCustom(): bool
    {
        return self::CUSTOM === $this;
    }
}
