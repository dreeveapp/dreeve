<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\Metric;

enum ActivityStreamMetricType: string
{
    case BEST_AVERAGES = 'bestAverages';
    case VALUE_DISTRIBUTION = 'valueDistribution';
    case NORMALIZED_POWER = 'normalizedPower';
}
