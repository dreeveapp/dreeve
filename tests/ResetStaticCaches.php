<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Activity\ActivityIntensity;
use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\Stream\StreamBasedActivityPowerRepository;
use App\Infrastructure\Twig\HtmlTwigExtension;

trait ResetStaticCaches
{
    protected function resetStaticCaches(): void
    {
        EnrichedActivities::reset();
        DailyTrainingLoad::$cachedLoad = [];
        ActivityIntensity::$cachedIntensities = [];
        StreamBasedActivityPowerRepository::$cachedPowerOutputs = [];
        HtmlTwigExtension::$seenIds = [];
    }
}
