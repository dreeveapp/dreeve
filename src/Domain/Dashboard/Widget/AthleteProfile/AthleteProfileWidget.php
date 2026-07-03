<?php

namespace App\Domain\Dashboard\Widget\AthleteProfile;

use App\Domain\Activity\ActivityIntensity;
use App\Domain\Activity\Math;
use App\Domain\Dashboard\Widget\AthleteProfile\FindAthleteProfileMetrics\FindAthleteProfileMetrics;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class AthleteProfileWidget implements Widget
{
    public function __construct(
        private QueryBus $queryBus,
        private ActivityIntensity $activityIntensity,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Athlete profile');
    }

    public function getTemplateName(): string
    {
        return 'widget--athlete-profile';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $chartData = [];
        foreach ([30, 90] as $lastXDays) {
            $findAthleteProfileMetricsResponse = $this->queryBus->ask(new FindAthleteProfileMetrics(DateRange::lastXDays($now, $lastXDays)));
            $numberOfActivities = $findAthleteProfileMetricsResponse->getNumberOfActivities();
            if (0 === $numberOfActivities) {
                continue;
            }

            $movingTimeInHours = $findAthleteProfileMetricsResponse->getMovingTime();
            $numberOfActiveDays = $findAthleteProfileMetricsResponse->getNumberOfActiveDays();

            // VOLUME: 10 h/week = very active amateur (works across sports).
            $weeklyHours = $movingTimeInHours->toFloat() / ($lastXDays / 7);
            $volume = min(100, $weeklyHours / 10 * 100);

            // CONSISTENCY: 5 days/week = excellent consistency.
            $activeDaysRatio = $numberOfActiveDays / $lastXDays;
            $consistency = min(100, $activeDaysRatio / 0.7 * 100);

            // INTENSITY: Use IF / TRIMP.
            $intensities = [];
            foreach ($findAthleteProfileMetricsResponse->getActivityIds() as $activityId) {
                $intensities[] = $this->activityIntensity->calculate($activityId);
            }
            $intensity = min(100, Math::median($intensities));

            // DURATION: Median > 90 min = endurance-leaning athlete.
            $activityMovingTimes = $findAthleteProfileMetricsResponse->getActivityMovingTimesInSeconds();
            $medianInSeconds = Math::median($activityMovingTimes);
            $duration = min(100, Seconds::from($medianInSeconds)->toMinute()->toFloat() / 90 * 100);

            // DENSITY: 2h per training day = high density.
            $averageTrainingDurationPerDay = $movingTimeInHours->toFloat() / $numberOfActiveDays;
            $density = min(100, $averageTrainingDurationPerDay / 2 * 100);

            // VARIETY: 0.5 is the anchor that defines "max meaningful variety"
            $dominantSportFraction = $findAthleteProfileMetricsResponse->getNumberOfActivitiesInMostPopularActivityType() / $numberOfActivities;
            $variety = min(100, (1 - $dominantSportFraction) / 0.5 * 100);

            $chartData[$lastXDays] = [
                round($volume),
                round($consistency),
                round($intensity),
                round($duration),
                round($density),
                round($variety),
            ];
        }

        if ([] === $chartData) {
            return null;
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'athleteProfileChart' => Json::encode(
                AthleteProfileChart::create(
                    chartData: $chartData,
                    translator: $this->translator
                )->build()
            ),
        ]);
    }
}
