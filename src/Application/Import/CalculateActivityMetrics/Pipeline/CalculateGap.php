<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Gap\GapCalculator;
use App\Domain\Activity\Gap\GapSegment;
use App\Domain\Activity\Split\ActivitySplit;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Split\ActivitySplits;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 30)]
final readonly class CalculateGap implements CalculateActivityMetricsStep
{
    private const float MINIMUM_GAP_PACE_FACTOR = 0.5;
    private const float MAXIMUM_GAP_PACE_FACTOR = 1.6;

    public function __construct(
        private ActivitySplitRepository $activitySplitRepository,
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated GAP for 0 activities');

        $countActivitiesProcessed = 0;
        foreach ($this->activitySplitRepository->findActivityIdsWithoutGap() as $activityId) {
            $trackPoints = $this->buildTrackPoints($activityId);
            if ([] === $trackPoints) {
                continue;
            }

            $gapCalculator = GapCalculator::create();

            /** @var list<GapSegment> $segments */
            $segments = iterator_to_array($gapCalculator->calculateSegments($trackPoints), false);
            if ([] === $segments) {
                continue;
            }

            $gapEnrichedSplits = ActivitySplits::empty()
                ->addMultiple($this->mapSegmentsToSplits($segments, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)))
                ->addMultiple($this->mapSegmentsToSplits($segments, $this->activitySplitRepository->findBy($activityId, UnitSystem::IMPERIAL)));

            foreach ($gapEnrichedSplits as $split) {
                $this->activitySplitRepository->update($split);
            }

            ++$countActivitiesProcessed;
            $progressIndicator->updateMessage(sprintf('=> Calculated GAP for %d activities', $countActivitiesProcessed));
        }

        $progressIndicator->finish(sprintf('=> Calculated GAP for %d activities', $countActivitiesProcessed));
    }

    /**
     * @return list<array{lat: float, lon: float, ele: float, timestamp: int}>
     */
    private function buildTrackPoints(ActivityId $activityId): array
    {
        $streams = $this->activityStreamRepository->findByActivityId($activityId);

        $latLng = $streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? [];
        $altitude = $streams->filterOnType(StreamType::ALTITUDE)?->getData() ?? [];
        $time = $streams->filterOnType(StreamType::TIME)?->getData() ?? [];
        if (in_array([], [$latLng, $altitude, $time], true)) {
            return [];
        }

        $moving = $streams->filterOnType(StreamType::MOVING)?->getData() ?? [];
        $hasMovingStream = [] !== $moving;
        $maxIndex = min(count($latLng), count($altitude), count($time));

        $trackPoints = [];
        for ($i = 0; $i < $maxIndex; ++$i) {
            if ($hasMovingStream && false === ($moving[$i] ?? false)) {
                continue;
            }

            $coordinate = $latLng[$i] ?? null;
            if (!is_array($coordinate)) {
                continue;
            }
            if (2 !== count($coordinate)) {
                continue;
            }

            $trackPoints[] = [
                'lat' => (float) $coordinate[0],
                'lon' => (float) $coordinate[1],
                'ele' => (float) $altitude[$i],
                'timestamp' => (int) $time[$i],
            ];
        }

        return $trackPoints;
    }

    /**
     * @param list<GapSegment> $gapSegments
     *
     * @return ActivitySplit[]
     */
    private function mapSegmentsToSplits(array $gapSegments, ActivitySplits $splits): array
    {
        if ($splits->isEmpty()) {
            return [];
        }

        $totalGapSegmentDistance = array_sum(array_map(
            static fn (GapSegment $segment): float => $segment->getDistanceInMeters(),
            $gapSegments,
        ));
        $totalSplitDistance = array_sum(array_map(
            static fn (ActivitySplit $split): float => $split->getDistance()->toFloat(),
            $splits->toArray(),
        ));
        $distanceScaleFactor = $totalGapSegmentDistance > 0.0 && $totalSplitDistance > 0.0 ? $totalSplitDistance / $totalGapSegmentDistance : 1.0;

        $splitItems = array_values($splits->toArray());
        $currentSplitIndex = 0;
        $distanceInCurrentSplit = 0.0;
        $durationInCurrentSplit = 0.0;
        $adjustedDistanceInCurrentSplit = 0.0;

        foreach ($gapSegments as $segment) {
            $remainingDistance = $segment->getDistanceInMeters() * $distanceScaleFactor;
            $remainingDuration = (float) $segment->getDurationInSeconds();
            $remainingAdjustedDistance = $segment->getDistanceInMeters() * $segment->getGapMultiplier() * $distanceScaleFactor;

            while ($remainingDistance > 0.0 && isset($splitItems[$currentSplitIndex])) {
                $split = $splitItems[$currentSplitIndex];
                $targetDistance = $split->getDistance()->toFloat();
                $remainingInSplit = $targetDistance - $distanceInCurrentSplit;

                if ($remainingInSplit <= 0.00001) {
                    $splitItems[$currentSplitIndex] = $this->finalizeSplitGap($split, $targetDistance, $distanceInCurrentSplit, $durationInCurrentSplit, $adjustedDistanceInCurrentSplit);
                    ++$currentSplitIndex;
                    $distanceInCurrentSplit = 0.0;
                    $durationInCurrentSplit = 0.0;
                    $adjustedDistanceInCurrentSplit = 0.0;
                    continue;
                }

                $distancePortion = min($remainingDistance, $remainingInSplit);
                $ratio = $distancePortion / $remainingDistance;
                $durationPortion = $remainingDuration * $ratio;
                $adjustedDistancePortion = $remainingAdjustedDistance * $ratio;

                $distanceInCurrentSplit += $distancePortion;
                $durationInCurrentSplit += $durationPortion;
                $adjustedDistanceInCurrentSplit += $adjustedDistancePortion;

                $remainingDistance -= $distancePortion;
                $remainingDuration -= $durationPortion;
                $remainingAdjustedDistance -= $adjustedDistancePortion;

                if ($distanceInCurrentSplit >= $targetDistance - 0.00001) {
                    $splitItems[$currentSplitIndex] = $this->finalizeSplitGap($split, $targetDistance, $distanceInCurrentSplit, $durationInCurrentSplit, $adjustedDistanceInCurrentSplit);
                    ++$currentSplitIndex;
                    $distanceInCurrentSplit = 0.0;
                    $durationInCurrentSplit = 0.0;
                    $adjustedDistanceInCurrentSplit = 0.0;
                }
            }

            if (!isset($splitItems[$currentSplitIndex])) {
                break;
            }
        }

        return $splitItems;
    }

    private function finalizeSplitGap(
        ActivitySplit $split,
        float $targetDistanceInMeters,
        float $distanceInCurrentSplit,
        float $durationInCurrentSplit,
        float $adjustedDistanceInCurrentSplit,
    ): ActivitySplit {
        if ($distanceInCurrentSplit < $targetDistanceInMeters - 0.00001 || $adjustedDistanceInCurrentSplit <= 0.0) {
            return $split;
        }

        return $split->withGapPace($this->resolveGapPace(
            $split,
            ($durationInCurrentSplit / $adjustedDistanceInCurrentSplit) * 1000.0,
            $distanceInCurrentSplit,
        ));
    }

    private function resolveGapPace(
        ActivitySplit $split,
        float $calculatedGapPaceInSecondsPerKm,
        float $distanceInCurrentSplit,
    ): SecPerKm {
        $actualPaceInSecondsPerKm = $split->getPaceInSecPerKm()->toFloat();

        $isValidGapPace = $distanceInCurrentSplit > 0.0
            && is_finite($calculatedGapPaceInSecondsPerKm)
            && $calculatedGapPaceInSecondsPerKm > 0.0;

        $gapPace = $isValidGapPace ? $calculatedGapPaceInSecondsPerKm : $actualPaceInSecondsPerKm;
        $minPace = $actualPaceInSecondsPerKm * self::MINIMUM_GAP_PACE_FACTOR;
        $maxPace = $actualPaceInSecondsPerKm * self::MAXIMUM_GAP_PACE_FACTOR;

        return SecPerKm::from(min($maxPace, max($minPace, $gapPace)));
    }
}
