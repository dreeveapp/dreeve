<?php

namespace App\Domain\Activity;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class DailyTrainingLoad
{
    /** @var array<string, int|null> */
    public static array $cachedLoad = [];

    public function __construct(
        private readonly EnrichedActivities $enrichedActivities,
        private readonly ActivityIntensity $activityIntensity,
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    public function calculate(SerializableDateTime $on): int
    {
        $cacheKey = $on->format('Y-m-d');
        if (array_key_exists($cacheKey, self::$cachedLoad) && null !== self::$cachedLoad[$cacheKey]) {
            return self::$cachedLoad[$cacheKey];
        }

        $activities = $this->enrichedActivities->findByStartDate(
            startDate: $on,
            activityType: null
        );
        $load = 0;

        $general = $this->settingsRepository->general();

        /** @var Activity $activity */
        foreach ($activities as $activity) {
            $movingTimeInSeconds = $activity->getMovingTimeInSeconds();
            if (ActivityType::RIDE === $activity->getSportType()->getActivityType() && ($normalizedPower = $activity->getNormalizedPower())) {
                try {
                    $intensity = $this->activityIntensity->calculatePowerBased($activity->getId());
                    $intensity /= 100;
                    $ftp = $general->getFtpHistory()->find(ActivityType::RIDE, $activity->getStartDate())->getFtp();
                    $load += ($movingTimeInSeconds * $normalizedPower * $intensity) / ($ftp->getValue() * 3600) * 100;

                    continue;
                } catch (CouldNotDetermineActivityIntensity|EntityNotFound) {
                }
            }

            try {
                $intensity = $this->activityIntensity->calculateHeartRateBased($activity->getId());
                $intensity /= 100;
                $athlete = $general->getAthlete();
                $bannisterKFactor = $athlete->isMale() ? 1.92 : 1.67;
                $trimp = Seconds::from($movingTimeInSeconds)->toMinute()->toFloat() * $intensity * exp($bannisterKFactor * $intensity);

                // Normalize TRIMP to hrTSS so it's on the same scale as power-based TSS.
                $athleteMaxHeartRate = $athlete->getMaxHeartRate($activity->getStartDate());
                $restingHeartRateFormula = $athlete->getRestingHeartRate($activity->getStartDate());
                $lthrBpm = $general->getHeartRateZoneConfiguration()->getHeartRateZonesFor(
                    sportType: $activity->getSportType(),
                    on: $activity->getStartDate()
                )->getZoneFive()->getRangeInBpm($athleteMaxHeartRate)[0];
                $hrrThreshold = ($lthrBpm - $restingHeartRateFormula) / ($athleteMaxHeartRate - $restingHeartRateFormula);
                $trimpThreshold = 60 * $hrrThreshold * exp($bannisterKFactor * $hrrThreshold);
                $load += ($trimp / $trimpThreshold) * 100;
            } catch (CouldNotDetermineActivityIntensity) {
            }
        }

        self::$cachedLoad[$cacheKey] = (int) round($load);

        return self::$cachedLoad[$cacheKey];
    }
}
