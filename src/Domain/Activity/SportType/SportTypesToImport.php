<?php

declare(strict_types=1);

namespace App\Domain\Activity\SportType;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<SportType>
 */
final class SportTypesToImport extends Collection
{
    public function getItemClassName(): string
    {
        return SportType::class;
    }

    /**
     * @param string[] $types
     */
    public static function from(array $types): self
    {
        if (0 === count($types)) {
            // Import all sport types.
            return self::fromArray(SportType::cases());
        }

        return self::fromArray(array_map(
            SportType::from(...),
            $types
        ));
    }
}
