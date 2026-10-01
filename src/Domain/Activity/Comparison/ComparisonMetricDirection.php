<?php

declare(strict_types=1);

namespace App\Domain\Activity\Comparison;

enum ComparisonMetricDirection: string
{
    case LOWER_IS_BETTER = 'lowerIsBetter';
    case HIGHER_IS_BETTER = 'higherIsBetter';
    case NEUTRAL = 'neutral';
}
