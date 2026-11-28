<?php

declare(strict_types=1);

namespace App\Domain\Media\Metadata;

enum ExifOrientation: int
{
    case NORMAL = 1;
    case MIRROR_HORIZONTAL = 2;
    case ROTATE_180 = 3;
    case MIRROR_VERTICAL = 4;
    case TRANSPOSE = 5;
    case ROTATE_90_CW = 6;
    case TRANSVERSE = 7;
    case ROTATE_90_CCW = 8;

    public function swapsWidthAndHeight(): bool
    {
        return in_array($this, [self::TRANSPOSE, self::ROTATE_90_CW, self::TRANSVERSE, self::ROTATE_90_CCW], true);
    }

    public function clockwiseRotationInDegrees(): int
    {
        return match ($this) {
            self::NORMAL, self::MIRROR_HORIZONTAL, self::MIRROR_VERTICAL => 0,
            self::ROTATE_180 => 180,
            self::TRANSPOSE, self::ROTATE_90_CW, self::TRANSVERSE => 90,
            self::ROTATE_90_CCW => 270,
        };
    }

    public function mirrorsHorizontally(): bool
    {
        return in_array($this, [self::MIRROR_HORIZONTAL, self::TRANSPOSE], true);
    }

    public function mirrorsVertically(): bool
    {
        return in_array($this, [self::MIRROR_VERTICAL, self::TRANSVERSE], true);
    }
}
