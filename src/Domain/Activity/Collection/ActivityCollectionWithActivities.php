<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\WorldType;
use App\Domain\Integration\Weather\OpenMeteo\Weather;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Temperature\Celsius;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ActivityCollectionWithActivities
{
    private function __construct(
        private ActivityCollection $activityCollection,
        private Activities $activities,
    ) {
    }

    public static function create(
        ActivityCollection $activityCollection,
        Activities $activities,
    ): self {
        return new self(
            activityCollection: $activityCollection,
            activities: $activities->usort(static fn (Activity $a, Activity $b): int => $a->getStartDate() <=> $b->getStartDate()),
        );
    }

    public function getActivityCollection(): ActivityCollection
    {
        return $this->activityCollection;
    }

    public function getActivities(): Activities
    {
        return $this->activities;
    }

    public function getActivitiesWithARealWorldRoute(): Activities
    {
        return $this->activities->filter(static fn (Activity $activity): bool => WorldType::REAL_WORLD === $activity->getWorldType()
            && $activity->getEncodedPolyline() instanceof EncodedPolyline);
    }

    public function getCoverImagePath(): ?string
    {
        if ($coverImagePath = $this->activityCollection->getCoverImagePath()) {
            return $coverImagePath;
        }

        foreach ($this->activities as $activity) {
            if ($localImagePaths = $activity->getLocalImagePaths()) {
                return reset($localImagePaths);
            }
        }

        return null;
    }

    public function getStartDate(): SerializableDateTime
    {
        $activity = $this->activities->getFirst();
        assert($activity instanceof Activity);

        return $activity->getStartDate();
    }

    public function getEndDate(): SerializableDateTime
    {
        $activity = $this->activities->getLast();
        assert($activity instanceof Activity);

        return $activity->getStartDate();
    }

    public function getNumberOfDays(): int
    {
        return (int) $this->getStartDate()->setTime(0, 0)->diff($this->getEndDate()->setTime(0, 0))->days + 1;
    }

    public function getNumberOfRestDays(): int
    {
        return $this->getNumberOfDays() - count(array_unique($this->activities->map(
            static fn (Activity $activity): string => $activity->getStartDate()->format('Y-m-d'),
        )));
    }

    public function getDistance(): Kilometer
    {
        return Kilometer::from($this->activities->sum(static fn (Activity $activity): float => $activity->getDistance()->toFloat()));
    }

    public function getElevation(): Meter
    {
        return Meter::from($this->activities->sum(static fn (Activity $activity): float => $activity->getElevation()->toFloat()));
    }

    public function getMovingTime(): Seconds
    {
        return Seconds::from($this->activities->sum(static fn (Activity $activity): int => $activity->getMovingTimeInSeconds()));
    }

    public function getElapsedTime(): Seconds
    {
        return Seconds::from($this->activities->sum(static fn (Activity $activity): int => $activity->getElapsedTimeInSeconds()));
    }

    /**
     * @return string[]
     */
    public function getCountryCodes(): array
    {
        return array_values(array_unique(array_merge(...$this->activities->map(
            static fn (Activity $activity): array => $activity->getRouteGeography()->getPassedThroughCountries(),
        ))));
    }

    /**
     * @return string[]
     */
    public function getPlaces(): array
    {
        return array_values(array_unique(array_filter($this->activities->map(
            static fn (Activity $activity): ?string => $activity->getRouteGeography()->getStartingPointCity(),
        ))));
    }

    public function getMinTemperature(): ?Celsius
    {
        $temperatures = array_map(
            static fn (Weather $weather): float => $weather->getTemperatureInCelsius()->toFloat(),
            $this->getWeathers(),
        );

        return [] === $temperatures ? null : Celsius::from(min($temperatures));
    }

    public function getMaxTemperature(): ?Celsius
    {
        $temperatures = array_map(
            static fn (Weather $weather): float => $weather->getTemperatureInCelsius()->toFloat(),
            $this->getWeathers(),
        );

        return [] === $temperatures ? null : Celsius::from(max($temperatures));
    }

    public function getMostCommonWeatherCondition(): ?string
    {
        $conditions = array_count_values(array_map(
            static fn (Weather $weather): string => $weather->getWeatherCodeDescription(),
            $this->getWeathers(),
        ));
        arsort($conditions);

        return [] === $conditions ? null : array_key_first($conditions);
    }

    /**
     * @return Weather[]
     */
    private function getWeathers(): array
    {
        return array_values(array_filter($this->activities->map(
            static fn (Activity $activity): ?Weather => $activity->getWeather(),
        )));
    }
}
