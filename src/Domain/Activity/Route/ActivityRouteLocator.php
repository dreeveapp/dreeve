<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
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

    public function locateAt(Activity $activity, SerializableDateTime $on): ?Coordinate
    {
        $streams = $this->activityStreamRepository->findByActivityId($activity->getId());
        $times = array_values($streams->filterOnType(StreamType::TIME)?->getData() ?? []);
        $latLngs = array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []);
        if ([] === $times || [] === $latLngs) {
            return null;
        }

        $offset = $on->getTimestamp() - $activity->getStartDate()->getTimestamp();
        if ($offset < 0 || $offset > array_last($times)) {
            return null;
        }

        $low = 0;
        $high = count($times) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            if ($times[$middle] < $offset) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        $after = $low;
        $before = isset($times[$after]) && $times[$after] === $offset ? $after : $after - 1;

        while ($before >= 0 && !isset($latLngs[$before][0], $latLngs[$before][1])) {
            --$before;
        }
        while (isset($times[$after]) && !isset($latLngs[$after][0], $latLngs[$after][1])) {
            ++$after;
        }

        $hasBefore = $before >= 0;
        $hasAfter = isset($times[$after]);
        if (!$hasBefore && !$hasAfter) {
            return null;
        }

        if (!$hasAfter) {
            [$latitude, $longitude] = $latLngs[$before];
        } elseif (!$hasBefore || $before === $after) {
            [$latitude, $longitude] = $latLngs[$after];
        } else {
            $gap = $times[$after] - $times[$before];
            $fraction = ($offset - $times[$before]) / $gap;
            if ($gap > self::MAX_INTERPOLATION_GAP_IN_SECONDS) {
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
