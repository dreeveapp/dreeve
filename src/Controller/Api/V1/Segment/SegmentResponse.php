<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Segment;

use App\Domain\Segment\Search\SegmentSearchResult;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Infrastructure\Http\Api\ProvidePagination;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SegmentResponse extends JsonResponse
{
    use ProvidePagination;

    /**
     * @param Overview<SegmentSearchResult> $overview
     */
    public static function list(Overview $overview, Pagination $pagination): self
    {
        return new self([
            'segments' => array_map(
                static fn (SegmentSearchResult $result): array => self::segment(
                    segment: $result->getSegment(),
                    numberOfTimesRidden: $result->getNumberOfTimesRidden(),
                    bestEffort: $result->getBestEffort(),
                ),
                $overview->getItems()
            ),
            'pagination' => self::buildPagination(
                pagination: $pagination,
                total: $overview->getTotal(),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function segment(Segment $segment, int $numberOfTimesRidden, ?SegmentEffort $bestEffort): array
    {
        $startingCoordinate = $segment->getStartingCoordinate();

        return [
            'id' => (string) $segment->getId(),
            'name' => (string) $segment->getOriginalName(),
            'type' => $segment->getType()->value,
            'sportType' => $segment->getSportType()->value,
            'distanceInMeter' => $segment->getDistance()->toMeter()->toInt(),
            'averageGradient' => $segment->getAverageGradient(),
            'maxGradient' => $segment->getMaxGradient(),
            'startLatLng' => $startingCoordinate instanceof Coordinate ? [
                $startingCoordinate->getLatitude()->toFloat(),
                $startingCoordinate->getLongitude()->toFloat(),
            ] : null,
            'encodedPolyline' => (string) $segment->getPolyline() ?: null,
            'countryCode' => $segment->getCountryCode(),
            'numberOfTimesRidden' => $numberOfTimesRidden,
            'bestEffort' => $bestEffort instanceof SegmentEffort ? [
                'activityId' => (string) $bestEffort->getActivityId(),
                'startDateLocal' => $bestEffort->getStartDateTime()->format('Y-m-d\TH:i:s'),
                'elapsedTimeInSeconds' => $bestEffort->getElapsedTimeInSeconds(),
                'averagePower' => $bestEffort->getAverageWatts(),
            ] : null,
            'stravaUrl' => $segment->getStravaUrl(),
        ];
    }
}
