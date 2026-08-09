<?php

declare(strict_types=1);

namespace App\Domain\Activity\Image;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\ValueObject\Collection;

final class Images extends Collection
{
    public function getActivityIds(): ActivityIds
    {
        return ActivityIds::fromArray($this->map(
            fn (Image $image): ActivityId => $image->getActivityId()
        ))->unique();
    }

    public function getItemClassName(): string
    {
        return Image::class;
    }
}
