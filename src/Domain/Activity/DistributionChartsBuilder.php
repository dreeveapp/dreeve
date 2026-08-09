<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Velocity\Pace;
use App\Infrastructure\Serialization\Json;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DistributionChartsBuilder
{
    public function __construct(
        private ActivityStreamMetricRepository $activityStreamMetricRepository,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array{title: string, data: string}>
     */
    public function buildFor(Activity $activity): array
    {
        $general = $this->settingsRepository->general();
        $athlete = $general->getAthlete();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $activityType = $activity->getSportType()->getActivityType();

        $valueDistributionMetrics = $this->activityStreamMetricRepository->findByActivityIdAndMetricType(
            $activity->getId(),
            ActivityStreamMetricType::VALUE_DISTRIBUTION
        );

        $distributionCharts = [];
        $heartRateDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::HEART_RATE)?->getData() ?? [];
        if ($activity->getAverageHeartRate() && [] !== $heartRateDistribution) {
            $distributionCharts[] = [
                'title' => $this->translator->trans('Heart rate'),
                'data' => Json::encode(HeartRateDistributionChart::create(
                    heartRateData: $heartRateDistribution,
                    averageHeartRate: $activity->getAverageHeartRate(),
                    athleteMaxHeartRate: $athlete->getMaxHeartRate($activity->getStartDate()),
                    heartRateZones: $general->getHeartRateZoneConfiguration()->getHeartRateZonesFor(
                        sportType: $activity->getSportType(),
                        on: $activity->getStartDate()
                    )
                )->build()),
            ];
        }

        $powerDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::WATTS)?->getData() ?? [];
        if ($activityType->supportsPowerData() && $activity->getAveragePower()
            && count($powerDistribution) > 1) {
            $ftp = null;
            try {
                $ftp = $general->getFtpHistory()->find(
                    activityType: $activityType,
                    on: $activity->getStartDate()
                );
            } catch (EntityNotFound) {
            }

            $powerDistributionChart = PowerDistributionChart::create(
                powerData: $powerDistribution,
                averagePower: $activity->getAveragePower(),
                ftp: $ftp,
            )->build();

            if (!is_null($powerDistributionChart)) {
                $distributionCharts[] = [
                    'title' => $this->translator->trans('Power'),
                    'data' => Json::encode($powerDistributionChart),
                ];
            }
        }

        $velocityDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::VELOCITY)?->getData() ?? [];
        if ([] !== $velocityDistribution) {
            $velocityUnitPreference = $activity->getSportType()->getVelocityDisplayPreference();

            $velocityDistributionChart = VelocityDistributionChart::create(
                velocityData: $velocityDistribution,
                averageSpeed: $activity->getAverageSpeed(),
                sportType: $activity->getSportType(),
                unitSystem: $unitSystem,
            )->build();

            if (!is_null($velocityDistributionChart)) {
                $distributionCharts[] = [
                    'title' => match (true) {
                        $velocityUnitPreference instanceof Pace => $this->translator->trans('Pace'),
                        default => $this->translator->trans('Speed'),
                    },
                    'data' => Json::encode($velocityDistributionChart),
                ];
            }
        }

        $cadenceDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::CADENCE)?->getData() ?? [];
        if ($activity->getAverageCadence() && count($cadenceDistribution) > 1) {
            $cadenceDistributionChart = CadenceDistributionChart::create(
                cadenceData: $cadenceDistribution,
                averageCadence: $activity->getAverageCadence(),
                activityType: $activityType,
            )->build();

            if (!is_null($cadenceDistributionChart)) {
                $distributionCharts[] = [
                    'title' => $this->translator->trans('Cadence'),
                    'data' => Json::encode($cadenceDistributionChart),
                ];
            }
        }

        return $distributionCharts;
    }
}
