<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Search\ActivitySearchResult;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ActivityResponse extends JsonResponse
{
    /**
     * @param Overview<ActivitySearchResult> $overview
     */
    public static function list(Overview $overview, Pagination $pagination): self
    {
        return new self([
            'activities' => array_map(
                static fn (ActivitySearchResult $result): array => self::activity(
                    $result->getActivity(),
                    $result->hasGpx()
                ),
                $overview->getItems()
            ),
            'pagination' => [
                'page' => $pagination->getCurrentPage(),
                'size' => $pagination->getLimit(),
                'total' => $overview->getTotal(),
                'totalPages' => (int) ceil($overview->getTotal() / $pagination->getLimit()),
            ],
        ]);
    }

    public static function detail(Activity $activity, bool $hasGpx): self
    {
        return new self(self::activity($activity, $hasGpx));
    }

    /**
     * @return array<string, mixed>
     */
    private static function activity(Activity $activity, bool $hasGpx): array
    {
        $startingCoordinate = $activity->getStartingCoordinate();
        $routeGeography = $activity->getRouteGeography();

        return [
            'id' => (string) $activity->getId(),
            'name' => $activity->getName(),
            'description' => $activity->getDescription(),
            'sportType' => $activity->getSportType()->value,
            'workoutType' => $activity->getWorkoutType()?->value,
            'worldType' => $activity->getWorldType()->value,
            'importSource' => $activity->getImportSource()->value,
            'startDateLocal' => $activity->getStartDate()->format('Y-m-d\TH:i:s'),
            'distanceInMeter' => $activity->getDistance()->toMeter()->toInt(),
            'elevationInMeter' => $activity->getElevation()->toInt(),
            'movingTimeInSeconds' => $activity->getMovingTimeInSeconds(),
            'elapsedTimeInSeconds' => $activity->getElapsedTimeInSeconds(),
            'averageSpeedInKmPerHour' => round($activity->getAverageSpeed()->toFloat(), 2),
            'maxSpeedInKmPerHour' => round($activity->getMaxSpeed()->toFloat(), 2),
            'calories' => $activity->getCalories(),
            'kilojoules' => $activity->getKilojoules(),
            'averageHeartRate' => $activity->getAverageHeartRate(),
            'maxHeartRate' => $activity->getMaxHeartRate(),
            'averagePower' => $activity->getAveragePower(),
            'maxPower' => $activity->getMaxPower(),
            'averageCadence' => $activity->getAverageCadence(),
            'isCommute' => $activity->isCommute(),
            'deviceName' => $activity->getDeviceName(),
            'gearId' => (string) $activity->getGearId() ?: null,
            'location' => [
                'countryCode' => $routeGeography->getStartingPointCountryCode(),
                'state' => $routeGeography->getStartingPointState(),
                'city' => $routeGeography->getStartingPointCity(),
            ],
            'passedThroughCountries' => $routeGeography->getPassedThroughCountries(),
            'startLatLng' => $startingCoordinate instanceof Coordinate ? [
                $startingCoordinate->getLatitude()->toFloat(),
                $startingCoordinate->getLongitude()->toFloat(),
            ] : null,
            'encodedPolyline' => (string) $activity->getEncodedPolyline() ?: null,
            'hasGpx' => $hasGpx,
        ];
    }
}
