<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\Metric;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;

interface ActivityStreamMetricRepository
{
    public function add(ActivityStreamMetric $metric): void;

    public function deleteForActivity(ActivityId $activityId): void;

    public function findActivityIdsWithoutBestAverages(): ActivityIds;

    public function findActivityIdsWithoutNormalizedPower(): ActivityIds;

    public function findActivityIdsWithoutDistributionValues(): ActivityIds;

    public function findByActivityIdAndMetricType(ActivityId $activityId, ActivityStreamMetricType $metricType): ActivityStreamMetrics;

    public function findByActivityId(ActivityId $activityId): ActivityStreamMetrics;
}
