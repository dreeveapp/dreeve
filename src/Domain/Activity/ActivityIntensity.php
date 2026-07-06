<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;

final class ActivityIntensity
{
    /** @var array<string, int|null> */
    public static array $cachedIntensities = [];

    public function __construct(
        private readonly EnrichedActivities $enrichedActivities,
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    public function calculate(ActivityId $activityId): int
    {
        $cacheKey = (string) $activityId;
        if (array_key_exists($cacheKey, self::$cachedIntensities) && null !== self::$cachedIntensities[$cacheKey]) {
            return self::$cachedIntensities[$cacheKey];
        }

        try {
            return $this->calculatePowerBased($activityId);
        } catch (CouldNotDetermineActivityIntensity) {
        }

        try {
            return $this->calculateHeartRateBased($activityId);
        } catch (CouldNotDetermineActivityIntensity) {
        }

        self::$cachedIntensities[$cacheKey] = 0;

        return 0;
    }

    public function calculatePowerBased(ActivityId $activityId): int
    {
        $activity = $this->enrichedActivities->find($activityId);
        if (ActivityType::RIDE !== $activity->getSportType()->getActivityType()) {
            throw new CouldNotDetermineActivityIntensity('Activity is not a ride');
        }

        $cacheKey = (string) $activity->getId();
        if (array_key_exists($cacheKey, self::$cachedIntensities) && null !== self::$cachedIntensities[$cacheKey]) {
            return self::$cachedIntensities[$cacheKey];
        }

        if (!$normalizedPower = $activity->getNormalizedPower()) {
            throw new CouldNotDetermineActivityIntensity('Activity has no normalized power');
        }

        try {
            $ftp = $this->settingsRepository->general()->getFtpHistory()->find(ActivityType::RIDE, $activity->getStartDate())->getFtp();
            // IF = Normalized Power / FTP
            $intensity = (int) round(($normalizedPower / $ftp->getValue()) * 100);
            self::$cachedIntensities[$cacheKey] = $intensity;

            return self::$cachedIntensities[$cacheKey];
        } catch (EntityNotFound) {
        }

        throw new CouldNotDetermineActivityIntensity('Ftp not found');
    }

    public function calculateHeartRateBased(ActivityId $activityId): int
    {
        $cacheKey = (string) $activityId;
        if (array_key_exists($cacheKey, self::$cachedIntensities) && null !== self::$cachedIntensities[$cacheKey]) {
            return self::$cachedIntensities[$cacheKey];
        }

        $activity = $this->enrichedActivities->find($activityId);
        if (!$averageHeartRate = $activity->getAverageHeartRate()) {
            throw new CouldNotDetermineActivityIntensity();
        }

        $athlete = $this->settingsRepository->general()->getAthlete();
        $athleteRestingHeartRate = $athlete->getRestingHeartRate($activity->getStartDate());
        $athleteMaxHeartRate = $athlete->getMaxHeartRate($activity->getStartDate());

        $intensity = (int) round(($averageHeartRate - $athleteRestingHeartRate) / ($athleteMaxHeartRate - $athleteRestingHeartRate) * 100);
        self::$cachedIntensities[$cacheKey] = $intensity;

        return self::$cachedIntensities[$cacheKey];
    }
}
