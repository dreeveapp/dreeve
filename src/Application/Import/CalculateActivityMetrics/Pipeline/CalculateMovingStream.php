<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 70)]
final readonly class CalculateMovingStream implements CalculateActivityMetricsStep
{
    private const float MOVING_SPEED_THRESHOLD_IN_METERS_PER_SECOND = 0.5;

    public function __construct(
        private ActivityStreamRepository $activityStreamRepository,
        private Clock $clock,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated moving stream for 0 activities');

        $countActivitiesProcessed = 0;
        $activityIdsWithMovingStream = $this->activityStreamRepository->findActivityIdsByStreamType(StreamType::MOVING);

        foreach ($this->activityStreamRepository->findActivityIdsByStreamType(StreamType::TIME) as $activityId) {
            if ($activityIdsWithMovingStream->has($activityId)) {
                continue;
            }

            $movingData = $this->determineMovingData($this->activityStreamRepository->findByActivityId($activityId));
            if (null === $movingData) {
                continue;
            }

            $this->activityStreamRepository->add(ActivityStream::create(
                activityId: $activityId,
                streamType: StreamType::MOVING,
                streamData: $movingData,
                createdOn: $this->clock->getCurrentDateTimeImmutable(),
            ));

            ++$countActivitiesProcessed;
            $progressIndicator->updateMessage(sprintf('=> Calculated moving stream for %d activities', $countActivitiesProcessed));
        }

        $progressIndicator->finish(sprintf('=> Calculated moving stream for %d activities', $countActivitiesProcessed));
    }

    /**
     * @return list<bool>|null
     */
    private function determineMovingData(ActivityStreams $streams): ?array
    {
        $speeds = $this->resolveSpeeds($streams);
        if (null === $speeds) {
            return null;
        }

        return array_map(
            // An unknown speed (a gap in the data) counts as moving so we never
            // discard time we cannot prove was spent stationary.
            static fn (?float $speed): bool => null === $speed || $speed >= self::MOVING_SPEED_THRESHOLD_IN_METERS_PER_SECOND,
            $speeds,
        );
    }

    /**
     * @return list<float|null>|null
     */
    private function resolveSpeeds(ActivityStreams $streams): ?array
    {
        $velocity = array_values($streams->filterOnType(StreamType::VELOCITY)?->getData() ?? []);
        if ($this->hasNumericValue($velocity)) {
            return array_map(
                static fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null,
                $velocity,
            );
        }

        $time = array_values($streams->filterOnType(StreamType::TIME)?->getData() ?? []);
        if ([] === $time) {
            return null;
        }

        $distance = array_values($streams->filterOnType(StreamType::DISTANCE)?->getData() ?? []);
        if ($this->hasNumericValue($distance)) {
            return $this->speedsFromDistance($distance, $time);
        }

        $latLng = array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []);
        if ([] !== array_filter($latLng, is_array(...))) {
            return $this->speedsFromCoordinates($latLng, $time);
        }

        return null;
    }

    /**
     * @param list<mixed> $distance
     * @param list<mixed> $time
     *
     * @return list<float|null>
     */
    private function speedsFromDistance(array $distance, array $time): array
    {
        // The first point has no predecessor, so its speed is unknown.
        $speeds = [null];
        $count = min(count($distance), count($time));
        for ($i = 1; $i < $count; ++$i) {
            $deltaTime = (float) $time[$i] - (float) $time[$i - 1];
            $deltaDistance = (float) $distance[$i] - (float) $distance[$i - 1];
            $speeds[] = $deltaTime > 0.0 ? max(0.0, $deltaDistance / $deltaTime) : null;
        }

        return $speeds;
    }

    /**
     * @param list<mixed> $latLng
     * @param list<mixed> $time
     *
     * @return list<float|null>
     */
    private function speedsFromCoordinates(array $latLng, array $time): array
    {
        $speeds = [null];
        $count = min(count($latLng), count($time));
        for ($i = 1; $i < $count; ++$i) {
            $current = $latLng[$i];
            $previous = $latLng[$i - 1];
            $deltaTime = (float) $time[$i] - (float) $time[$i - 1];
            if (!is_array($current) || !is_array($previous) || $deltaTime <= 0.0) {
                $speeds[] = null;
                continue;
            }

            $meters = GeoMath::haversineDistance(
                (float) $current[0], (float) $current[1],
                (float) $previous[0], (float) $previous[1],
            );
            $speeds[] = $meters / $deltaTime;
        }

        return $speeds;
    }

    /**
     * @param list<mixed> $values
     */
    private function hasNumericValue(array $values): bool
    {
        return array_any($values, fn (mixed $value): bool => is_numeric($value));
    }
}
