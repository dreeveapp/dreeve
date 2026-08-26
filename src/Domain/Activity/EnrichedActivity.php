<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Activity\Stream\PowerOutput;
use App\Domain\Activity\Stream\PowerOutputs;
use App\Domain\Integration\AI\SupportsAITooling;

final readonly class EnrichedActivity implements SupportsAITooling
{
    private function __construct(
        private Activity $activity,
        private ?int $normalizedPower,
        private ?int $maxCadence,
        private ?string $gearName,
        private PowerOutputs $bestPowerOutputs,
    ) {
    }

    public static function fromState(
        Activity $activity,
        ?int $normalizedPower,
        ?int $maxCadence,
        ?string $gearName,
        PowerOutputs $bestPowerOutputs,
    ): self {
        return new self(
            activity: $activity,
            normalizedPower: $normalizedPower,
            maxCadence: $maxCadence,
            gearName: $gearName,
            bestPowerOutputs: $bestPowerOutputs,
        );
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getNormalizedPower(): ?int
    {
        return $this->normalizedPower;
    }

    public function getMaxCadence(): ?int
    {
        return $this->maxCadence;
    }

    public function getGearName(): ?string
    {
        return $this->gearName;
    }

    public function hasDetailedPowerData(): bool
    {
        return !$this->bestPowerOutputs->isEmpty();
    }

    /**
     * @return array<string, string|int|float>
     */
    public function getSortables(): array
    {
        $powerSortables = [];
        foreach (ActivityPowerRepository::TIME_INTERVALS_IN_SECONDS_REDACTED as $interval) {
            if (!($bestAverage = $this->getBestAveragePowerForTimeInterval($interval)) instanceof PowerOutput) {
                continue;
            }

            $powerSortables[sprintf('power-%ss', $interval)] = $bestAverage->getPower();
        }

        return array_filter(array_merge($this->activity->getSortables(), $powerSortables));
    }

    public function getBestAveragePowerForTimeInterval(int $timeInterval): ?PowerOutput
    {
        return $this->bestPowerOutputs->find(
            fn (PowerOutput $bestPowerOutput): bool => $bestPowerOutput->getTimeIntervalInSeconds() === $timeInterval
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function exportForAITooling(): array
    {
        return [
            'id' => $this->activity->getId()->toUnprefixedString(),
            'startDateTime' => $this->activity->getStartDate(),
            'sportType' => $this->activity->getSportType()->value,
            'name' => $this->activity->getName(),
            'description' => $this->activity->getDescription(),
            'distanceInKilometer' => $this->activity->getDistance(),
            'elevationInMeter' => $this->activity->getElevation(),
            'startingCoordinate' => $this->activity->getStartingCoordinate(),
            'caloriesBurnt' => $this->activity->getCalories(),
            'averagePowerInWatts' => $this->activity->getAveragePower(),
            'maxPowerInWatts' => $this->activity->getMaxPower(),
            'averageSpeed' => $this->activity->getAverageSpeed(),
            'maxSpeed' => $this->activity->getMaxSpeed(),
            'averageHeartRate' => $this->activity->getAverageHeartRate(),
            'maxHeartRate' => $this->activity->getMaxHeartRate(),
            'averageCadence' => $this->activity->getAverageCadence(),
            'movingTimeInSeconds' => $this->activity->getMovingTimeInSeconds(),
            'recordedOnDevice' => $this->activity->getDeviceName(),
            'totalImageCount' => $this->activity->getTotalImageCount(),
            'startingPointCountryCode' => $this->activity->getRouteGeography()->getStartingPointCountryCode(),
            'startingPointState' => $this->activity->getRouteGeography()->getStartingPointState(),
            'weather' => $this->activity->getWeather(),
            'gearId' => $this->activity->getGearId()?->toUnprefixedString(),
            'gearName' => $this->gearName,
            'isCommute' => $this->activity->isCommute(),
            'isGroupActivity' => $this->activity->isGroupActivity(),
            'workoutType' => $this->activity->getWorkoutType()?->value,
        ];
    }
}
