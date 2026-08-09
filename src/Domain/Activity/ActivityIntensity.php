<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class ActivityIntensity
{
    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function calculate(EnrichedActivity $enrichedActivity): int
    {
        try {
            return $this->calculatePowerBased($enrichedActivity);
        } catch (CouldNotDetermineActivityIntensity) {
        }

        try {
            return $this->calculateHeartRateBased($enrichedActivity);
        } catch (CouldNotDetermineActivityIntensity) {
        }

        return 0;
    }

    public function calculatePowerBased(EnrichedActivity $enrichedActivity): int
    {
        $activity = $enrichedActivity->getActivity();
        if (ActivityType::RIDE !== $activity->getSportType()->getActivityType()) {
            throw new CouldNotDetermineActivityIntensity('Activity is not a ride');
        }

        if (!$normalizedPower = $enrichedActivity->getNormalizedPower()) {
            throw new CouldNotDetermineActivityIntensity('Activity has no normalized power');
        }

        try {
            $ftp = $this->settingsRepository->general()->getFtpHistory()->find(ActivityType::RIDE, $activity->getStartDate())->getFtp();
        } catch (EntityNotFound) {
            throw new CouldNotDetermineActivityIntensity('Ftp not found');
        }

        // IF = Normalized Power / FTP
        return (int) round(($normalizedPower / $ftp->getValue()) * 100);
    }

    public function calculateHeartRateBased(EnrichedActivity $enrichedActivity): int
    {
        $activity = $enrichedActivity->getActivity();
        if (!$averageHeartRate = $activity->getAverageHeartRate()) {
            throw new CouldNotDetermineActivityIntensity();
        }

        $athlete = $this->settingsRepository->general()->getAthlete();
        $athleteRestingHeartRate = $athlete->getRestingHeartRate($activity->getStartDate());
        $athleteMaxHeartRate = $athlete->getMaxHeartRate($activity->getStartDate());

        return (int) round(($averageHeartRate - $athleteRestingHeartRate) / ($athleteMaxHeartRate - $athleteRestingHeartRate) * 100);
    }
}
