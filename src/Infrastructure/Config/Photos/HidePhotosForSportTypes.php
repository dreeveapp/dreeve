<?php

declare(strict_types=1);

namespace App\Infrastructure\Config\Photos;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ValueObject\BackedEnumCollection;

/**
 * @extends BackedEnumCollection<SportType>
 */
final class HidePhotosForSportTypes extends BackedEnumCollection
{
    public function getItemClassName(): string
    {
        return SportType::class;
    }
}
