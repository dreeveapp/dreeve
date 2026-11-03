<?php

declare(strict_types=1);

namespace App\Domain\Activity\SportType;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<SportType>
 */
class SportTypesSortingOrder extends Collection
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
        $allSportTypes = SportType::cases();
        if (0 === count($types)) {
            // Use default.
            return self::fromArray($allSportTypes);
        }

        $sortedSportTypes = [];

        foreach ($types as $type) {
            $sortedSportTypes[] = SportType::from($type);
        }

        foreach ($allSportTypes as $type) {
            if (in_array($type, $sortedSportTypes)) {
                continue;
            }

            $sortedSportTypes[] = $type;
        }

        return self::fromArray($sortedSportTypes);
    }
}
