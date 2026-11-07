<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\ValueObject\BackedEnumCollection;

/**
 * @extends BackedEnumCollection<CombinedStreamType>
 */
final class CombinedStreamTypes extends BackedEnumCollection
{
    public function getItemClassName(): string
    {
        return CombinedStreamType::class;
    }

    public static function othersFor(ActivityType $activityType): self
    {
        if (in_array($activityType, [ActivityType::RUN, ActivityType::WALK], true)) {
            return self::fromArray([
                CombinedStreamType::ALTITUDE,
                CombinedStreamType::HEART_RATE,
                CombinedStreamType::WATTS,
                CombinedStreamType::STEPS_PER_MINUTE,
                CombinedStreamType::PACE,
            ]);
        }

        return self::fromArray([
            CombinedStreamType::ALTITUDE,
            CombinedStreamType::HEART_RATE,
            CombinedStreamType::WATTS,
            CombinedStreamType::CADENCE,
            CombinedStreamType::VELOCITY,
        ]);
    }
}
