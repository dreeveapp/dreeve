<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Time\Clock\Clock;

final readonly class ActivityStreamsMapper
{
    public function __construct(
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, list<mixed>> $streamMap
     */
    public function fromStreamMap(array $streamMap, ActivityId $activityId): ActivityStreams
    {
        $streamMap = $this->fillGaps($streamMap);
        $createdOn = $this->clock->getCurrentDateTimeImmutable();

        $streams = ActivityStreams::empty();
        foreach ($streamMap as $type => $values) {
            if (!$streamType = StreamType::tryFrom($type)) {
                continue;
            }
            if ([] === array_filter($values, static fn (mixed $value): bool => null !== $value)) {
                continue;
            }
            $streams->add(ActivityStream::create(
                activityId: $activityId,
                streamType: $streamType,
                streamData: $values,
                createdOn: $createdOn,
            ));
        }

        return $streams;
    }

    /**
     * Devices record fields at different rates (heart rate every other second,
     * GPS twice per second but not every second, ...), leaving gaps that
     * downstream. Carry the last known value forward so every sample is complete.
     *
     * @param array<string, list<mixed>> $streams
     *
     * @return array<string, list<mixed>>
     */
    private function fillGaps(array $streams): array
    {
        foreach ($streams as $streamType => $values) {
            if (StreamType::TIME->value === $streamType) {
                continue;
            }

            $previous = null;
            foreach ($values as $i => $value) {
                if (null === $value) {
                    $streams[$streamType][$i] = $previous;
                    continue;
                }
                if (null === $previous && $i > 0) {
                    $streams[$streamType] = array_replace($streams[$streamType], array_fill(0, $i, $value));
                }
                $previous = $value;
            }
        }

        return $streams;
    }
}
