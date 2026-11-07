<?php

declare(strict_types=1);

namespace App\Infrastructure\Config\Photos;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ValueObject\EnumCollection;

/**
 * @extends EnumCollection<SportType>
 */
final class HidePhotosForSportTypes extends EnumCollection
{
    public function getItemClassName(): string
    {
        return SportType::class;
    }

    /**
     * @param string[] $sportTypes
     */
    public static function from(array $sportTypes): self
    {
        if (0 === count($sportTypes)) {
            return self::empty();
        }

        return self::fromArray(
            array_map(
                SportType::from(...),
                $sportTypes
            )
        );
    }
}
