<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ActivityRouteLocator
{
    private const int MAX_INTERPOLATION_GAP_IN_SECONDS = 30;

    public function __construct(
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function locateAtDateTime(Activity $activity, SerializableDateTime $on): ?Coordinate
    {
        $streams = $this->activityStreamRepository->findByActivityId($activity->getId());

        return $this->interpolate(
            axis: array_values($streams->filterOnType(StreamType::TIME)?->getData() ?? []),
            latLngs: array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []),
            value: $on->getTimestamp() - $activity->getStartDate()->getTimestamp(),
            maxInterpolationGap: self::MAX_INTERPOLATION_GAP_IN_SECONDS,
        );
    }

    public function locateAtDistance(Activity $activity, Meter $distance): ?Coordinate
    {
        $streams = $this->activityStreamRepository->findByActivityId($activity->getId());

        return $this->interpolate(
            axis: array_values($streams->filterOnType(StreamType::DISTANCE)?->getData() ?? []),
            latLngs: array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []),
            value: $distance->toFloat(),
            maxInterpolationGap: INF,
        );
    }

    /**
     * @param list<mixed> $axis
     * @param list<mixed> $latLngs
     */
    private function interpolate(array $axis, array $latLngs, int|float $value, int|float $maxInterpolationGap): ?Coordinate
    {
        if ([] === $axis || [] === $latLngs) {
            return null;
        }

        if ($value < 0 || $value > array_last($axis)) {
            return null;
        }

        $low = 0;
        $high = count($axis) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            if ($axis[$middle] < $value) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        $after = $low;
        $before = isset($axis[$after]) && (float) $axis[$after] === (float) $value ? $after : $after - 1;

        while ($before >= 0 && !isset($latLngs[$before][0], $latLngs[$before][1])) {
            --$before;
        }
        while (isset($axis[$after]) && !isset($latLngs[$after][0], $latLngs[$after][1])) {
            ++$after;
        }

        $hasBefore = $before >= 0;
        $hasAfter = isset($axis[$after]);
        if (!$hasBefore && !$hasAfter) {
            return null;
        }

        if (!$hasAfter) {
            [$latitude, $longitude] = $latLngs[$before];
        } elseif (!$hasBefore || $before === $after) {
            [$latitude, $longitude] = $latLngs[$after];
        } else {
            $gap = $axis[$after] - $axis[$before];
            $fraction = ($value - $axis[$before]) / $gap;
            if ($gap > $maxInterpolationGap) {
                [$latitude, $longitude] = $fraction <= 0.5 ? $latLngs[$before] : $latLngs[$after];
            } else {
                $latitude = $latLngs[$before][0] + ($latLngs[$after][0] - $latLngs[$before][0]) * $fraction;
                $longitude = $latLngs[$before][1] + ($latLngs[$after][1] - $latLngs[$before][1]) * $fraction;
            }
        }

        return Coordinate::createFromLatAndLng(
            latitude: Latitude::fromString((string) $latitude),
            longitude: Longitude::fromString((string) $longitude),
        );
    }
}
