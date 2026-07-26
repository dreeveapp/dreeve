<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;

final readonly class ActivityRouteCoordinates
{
    public function __construct(
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function first(Activity $activity): ?Coordinate
    {
        foreach ($this->fromLatLngStream($activity) as $coordinate) {
            return $coordinate;
        }

        return $activity->getStartingCoordinate();
    }

    public function last(Activity $activity): ?Coordinate
    {
        $last = null;
        foreach ($this->all($activity) as $coordinate) {
            $last = $coordinate;
        }

        return $last;
    }

    /**
     * @return iterable<Coordinate>
     */
    public function all(Activity $activity): iterable
    {
        $latLngStreamIsEmpty = true;
        foreach ($this->fromLatLngStream($activity) as $coordinate) {
            $latLngStreamIsEmpty = false;
            yield $coordinate;
        }

        if (!$latLngStreamIsEmpty) {
            return;
        }

        yield from $this->fromPolyline($activity);
    }

    /**
     * @return iterable<Coordinate>
     */
    private function fromLatLngStream(Activity $activity): iterable
    {
        $latLngStream = $this->activityStreamRepository
            ->findByActivityId($activity->getId())
            ->filterOnType(StreamType::LAT_LNG)?->getData() ?? [];

        foreach ($latLngStream as $point) {
            if (!is_array($point) || !isset($point[0], $point[1])) {
                continue;
            }

            yield $this->coordinate((float) $point[0], (float) $point[1]);
        }
    }

    /**
     * @return iterable<Coordinate>
     */
    private function fromPolyline(Activity $activity): iterable
    {
        $polyline = $activity->getEncodedPolyline();
        if (!$polyline instanceof EncodedPolyline) {
            return;
        }

        foreach ($polyline->decodeAndPairLatLng() as [$latitude, $longitude]) {
            yield $this->coordinate($latitude, $longitude);
        }
    }

    private function coordinate(float $latitude, float $longitude): Coordinate
    {
        return Coordinate::createFromLatAndLng(
            latitude: Latitude::fromString((string) $latitude),
            longitude: Longitude::fromString((string) $longitude),
        );
    }
}
