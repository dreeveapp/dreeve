<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DailyTrainingLoad
{
    public function __construct(
        private EnrichedActivityRepository $enrichedActivityRepository,
        private ActivityIntensity $activityIntensity,
        private SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @return array<string, int> the training load per day, keyed by 'Y-m-d'
     */
    public function calculateForDateRange(DateRange $dateRange): array
    {
        $from = SerializableDateTime::fromString($dateRange->getFrom()->format('Y-m-d').' 00:00:00');
        $till = SerializableDateTime::fromString($dateRange->getTill()->modify('+1 day')->format('Y-m-d').' 00:00:00');

        $activitiesPerDay = [];
        foreach ($this->enrichedActivityRepository->findByDateRange($from, $till) as $enrichedActivity) {
            $activitiesPerDay[$enrichedActivity->getActivity()->getStartDate()->format('Y-m-d')][] = $enrichedActivity;
        }

        $loadPerDay = [];
        for ($day = $from; $day < $till; $day = $day->modify('+1 day')) {
            $on = $day->format('Y-m-d');
            $loadPerDay[$on] = $this->calculateForDay($activitiesPerDay[$on] ?? []);
        }

        return $loadPerDay;
    }

    /**
     * @param EnrichedActivity[] $enrichedActivities
     */
    private function calculateForDay(array $enrichedActivities): int
    {
        $load = 0;
        $general = $this->settingsRepository->general();

        foreach ($enrichedActivities as $enrichedActivity) {
            $activity = $enrichedActivity->getActivity();
            $movingTimeInSeconds = $activity->getMovingTimeInSeconds();

            try {
                $intensity = $this->activityIntensity->calculatePowerBased($enrichedActivity) / 100;
                $normalizedPower = (int) $enrichedActivity->getNormalizedPower();
                $ftp = $general->getFtpHistory()->find(ActivityType::RIDE, $activity->getStartDate())->getFtp();
                $load += ($movingTimeInSeconds * $normalizedPower * $intensity) / ($ftp->getValue() * 3600) * 100;

                continue;
            } catch (CouldNotDetermineActivityIntensity|EntityNotFound) {
            }

            try {
                $intensity = $this->activityIntensity->calculateHeartRateBased($enrichedActivity) / 100;
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

        return (int) round($load);
    }
}
