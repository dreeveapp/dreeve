<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;
use App\Infrastructure\ValueObject\String\Slug;

final readonly class GpxFileName extends NonEmptyStringLiteral
{
    public static function for(Activity $activity): self
    {
        return self::fromString(sprintf(
            '%s-%s.gpx',
            $activity->getStartDate()->format('Y-m-d'),
            Slug::fromString($activity->getName()),
        ));
    }
}
