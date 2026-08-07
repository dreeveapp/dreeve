<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Activity\ActivityIntensity;
use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\Stream\StreamBasedActivityHeartRateRepository;
use App\Domain\Activity\Stream\StreamBasedActivityPowerRepository;
use App\Infrastructure\Twig\HtmlTwigExtension;

trait ResetStaticCaches
{
    /**
     * These caches are primed once per process and outlive the rolled back transaction,
     * so a test would otherwise read the previous test's activities.
     */
    protected function resetStaticCaches(): void
    {
        EnrichedActivities::reset();
        DailyTrainingLoad::$cachedLoad = [];
        ActivityIntensity::$cachedIntensities = [];
        StreamBasedActivityPowerRepository::$cachedPowerOutputs = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZones = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZonesPerActivityType = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZonesPerActivity = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZonesInLastXDays = [];
        ActivityTotals::$instance = null;
        HtmlTwigExtension::$seenIds = [];
    }
}
