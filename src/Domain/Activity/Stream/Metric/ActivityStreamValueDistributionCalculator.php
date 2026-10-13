<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\Metric;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\Knot;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;

final readonly class ActivityStreamValueDistributionCalculator
{
    public function __construct(
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityStreamMetricRepository $activityStreamMetricRepository,
        private ActivityRepository $activityRepository,
    ) {
    }

    public function calculateFor(ActivityId $activityId, UnitSystem $unitSystem): int
    {
        $activity = $this->activityRepository->find($activityId);
        $sportType = $activity->getSportType();

        $countCalculatedStreams = 0;
        foreach (StreamType::thatSupportDistributionValues() as $streamType) {
            $stream = null;
            try {
                $stream = $this->activityStreamRepository->findOneByActivityAndStreamType(
                    activityId: $activityId,
                    streamType: $streamType,
                );
            } catch (EntityNotFound) {
                $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
                    activityId: $activityId,
                    streamType: $streamType,
                    metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
                    data: [],
                ));

                continue;
            }

            $valueDistribution = [];
            if (StreamType::WATTS === $streamType) {
                /** @var array<int, int<1, max>> $valueDistribution */
                $valueDistribution = array_count_values(array_filter($stream->getData(), fn (mixed $item): bool => !is_null($item)));
                ksort($valueDistribution);
            } elseif (StreamType::HEART_RATE === $streamType) {
                /** @var array<int, int<1, max>> $valueDistribution */
                $valueDistribution = array_count_values(array_filter($stream->getData(), fn (mixed $item): bool => !is_null($item)));
                ksort($valueDistribution);
            } elseif (StreamType::CADENCE === $streamType) {
                /** @var array<int, int<1, max>> $valueDistribution */
                $valueDistribution = array_count_values(array_filter($stream->getData(), fn (mixed $item): bool => !is_null($item)));
                ksort($valueDistribution);
            } elseif (StreamType::VELOCITY === $streamType) {
                $velocityUnitPreference = $sportType->getVelocityDisplayPreference();
                $filteredValues = array_filter($stream->getData(), fn (mixed $item): bool => !is_null($item));

                $convertedValues = match (true) {
                    $velocityUnitPreference instanceof SecPer100Meter => array_map(
                        fn (float $item): int => (int) round(MetersPerSecond::from($item)->toSecPerKm()->toSecPer100Meter()->toFloat()),
                        $filteredValues
                    ),
                    $velocityUnitPreference instanceof SecPerKm => array_map(
                        fn (float $item): int => (int) round(MetersPerSecond::from($item)->toSecPerKm()->toUnitSystem($unitSystem)->toFloat()),
                        $filteredValues
                    ),
                    $velocityUnitPreference instanceof Knot => array_map(
                        fn (float $item): int => (int) round(MetersPerSecond::from($item)->toKmPerHour()->toKnots()->toFloat()),
                        $filteredValues
                    ),
                    UnitSystem::IMPERIAL === $unitSystem => array_map(
                        fn (float $item): int => (int) round(MetersPerSecond::from($item)->toKmPerHour()->toMph()->toFloat()),
                        $filteredValues
                    ),
                    default => array_map(
                        fn (float $item): int => (int) round(MetersPerSecond::from($item)->toKmPerHour()->toFloat()),
                        $filteredValues
                    ),
                };

                $valueDistribution = array_count_values($convertedValues);
                ksort($valueDistribution, SORT_NUMERIC);
            }

            if ([] !== $valueDistribution) {
                ++$countCalculatedStreams;
            }

            $this->activityStreamMetricRepository->add(ActivityStreamMetric::create(
                activityId: $activityId,
                streamType: $streamType,
                metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
                data: $valueDistribution,
            ));
        }

        return $countCalculatedStreams;
    }
}
