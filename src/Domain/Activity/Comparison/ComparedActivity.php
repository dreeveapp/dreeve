<?php

declare(strict_types=1);

namespace App\Domain\Activity\Comparison;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\EnrichedActivity;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ComparedActivity
{
    use ProvideTimeFormats;

    private function __construct(
        private ActivityId $activityId,
        private string $name,
        private SerializableDateTime $startDateTime,
        private int $movingTimeInSeconds,
        private KmPerHour $averageSpeed,
        private Meter $elevation,
        private ?int $normalizedPower,
        private ?int $averagePower,
        private ?int $averageHeartRate,
        private ?int $calories,
        private ?int $kilojoules,
    ) {
    }

    public static function fromEnrichedActivity(EnrichedActivity $enrichedActivity): self
    {
        $activity = $enrichedActivity->getActivity();

        return new self(
            activityId: $activity->getId(),
            name: $activity->getName(),
            startDateTime: $activity->getStartDate(),
            movingTimeInSeconds: $activity->getMovingTimeInSeconds(),
            averageSpeed: $activity->getAverageSpeed(),
            elevation: $activity->getElevation(),
            normalizedPower: $enrichedActivity->getNormalizedPower(),
            averagePower: $activity->getAveragePower(),
            averageHeartRate: $activity->getAverageHeartRate(),
            calories: $activity->getCalories(),
            kilojoules: $activity->getKilojoules(),
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStartDateTime(): SerializableDateTime
    {
        return $this->startDateTime;
    }

    public function getMovingTimeInSeconds(): int
    {
        return $this->movingTimeInSeconds;
    }

    public function getMovingTimeFormatted(): string
    {
        return $this->formatDurationAsClock($this->movingTimeInSeconds);
    }

    public function getEfficiencyFactor(): ?float
    {
        if (is_null($this->normalizedPower) || is_null($this->averageHeartRate) || 0 === $this->averageHeartRate) {
            return null;
        }

        return round($this->normalizedPower / $this->averageHeartRate, 3);
    }

    public function getSpeedToPowerRatio(UnitSystem $unitSystem): ?float
    {
        if (is_null($this->averagePower) || 0 === $this->averagePower) {
            return null;
        }

        return round($this->averageSpeed->toUnitSystem($unitSystem)->toFloat() / $this->averagePower, 4);
    }

    public function getValueFor(ComparisonMetric $metric, UnitSystem $unitSystem): ?float
    {
        return match ($metric) {
            ComparisonMetric::MOVING_TIME => (float) $this->movingTimeInSeconds,
            ComparisonMetric::AVERAGE_SPEED => round($this->averageSpeed->toUnitSystem($unitSystem)->toFloat(), 2),
            ComparisonMetric::ELEVATION => round($this->elevation->toUnitSystem($unitSystem)->toFloat(), 1),
            ComparisonMetric::NORMALIZED_POWER => is_null($this->normalizedPower) ? null : (float) $this->normalizedPower,
            ComparisonMetric::AVERAGE_POWER => is_null($this->averagePower) ? null : (float) $this->averagePower,
            ComparisonMetric::AVERAGE_HEART_RATE => is_null($this->averageHeartRate) ? null : (float) $this->averageHeartRate,
            ComparisonMetric::CALORIES => is_null($this->calories) ? null : (float) $this->calories,
            ComparisonMetric::KILOJOULES => is_null($this->kilojoules) ? null : (float) $this->kilojoules,
            ComparisonMetric::SPEED_TO_POWER_RATIO => $this->getSpeedToPowerRatio($unitSystem),
            ComparisonMetric::EFFICIENCY_FACTOR => $this->getEfficiencyFactor(),
        };
    }
}
