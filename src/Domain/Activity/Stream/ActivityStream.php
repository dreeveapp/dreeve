<?php

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Math;
use App\Domain\Integration\AI\SupportsAITooling;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityStream')]
#[ORM\Index(name: 'ActivityStream_activityIndex', columns: ['activityId'])]
#[ORM\Index(name: 'ActivityStream_streamTypeIndex', columns: ['streamType'])]
final readonly class ActivityStream implements SupportsAITooling
{
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $dataSize; // @phpstan-ignore property.onlyWritten

    /**
     * @param array<int, mixed> $data
     */
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Id, ORM\Column(type: 'string')]
        private StreamType $streamType,
        #[ORM\Column(type: 'datetime_immutable')]
        private SerializableDateTime $createdOn,
        #[ORM\Column(type: 'blob', nullable: true)]
        private array $data,
    ) {
        $this->dataSize = 0;
    }

    /**
     * @param array<int, mixed> $streamData
     */
    public static function create(
        ActivityId $activityId,
        StreamType $streamType,
        array $streamData,
        SerializableDateTime $createdOn,
    ): self {
        return new self(
            activityId: $activityId,
            streamType: $streamType,
            createdOn: $createdOn,
            data: $streamData,
        );
    }

    /**
     * @param array<int, mixed> $streamData
     */
    public static function fromState(
        ActivityId $activityId,
        StreamType $streamType,
        array $streamData,
        SerializableDateTime $createdOn,
    ): self {
        return new self(
            activityId: $activityId,
            streamType: $streamType,
            createdOn: $createdOn,
            data: $streamData,
        );
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return $this->createdOn;
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getStreamType(): StreamType
    {
        return $this->streamType;
    }

    public function applySimpleMovingAverage(int $windowSize): self
    {
        $count = count($this->data);
        if (0 === $count || $windowSize < 1) {
            return $this;
        }

        return clone ($this, [
            'data' => Math::movingAverage($this->data, $windowSize),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    public function getData(): array
    {
        if (StreamType::HEART_RATE === $this->getStreamType() && [] !== $this->data && max($this->data) > 300) {
            // Max BPM of 300, WTF? Must be faulty data.
            return [];
        }

        return $this->data;
    }

    public function hasValidData(): bool
    {
        return [] !== array_filter($this->data);
    }

    public function calculateBestAverageForTimeInterval(int $timeIntervalInSeconds): ?int
    {
        $data = $this->getData();
        $n = count($data);
        if ($n < $timeIntervalInSeconds) {
            // Not enough data.
            return null;
        }
        // Compute initial sum for the first X seconds.
        $currentSum = array_sum(array_slice($data, 0, $timeIntervalInSeconds));
        $maxSum = $currentSum;

        // Sliding window approach.
        for ($i = $timeIntervalInSeconds; $i < $n; ++$i) {
            $currentSum += $data[$i] - $data[$i - $timeIntervalInSeconds];
            $maxSum = max($maxSum, $currentSum);
        }

        return (int) round($maxSum / $timeIntervalInSeconds);
    }

    public function exportForAITooling(): array
    {
        if (!$data = $this->getData()) {
            return [];
        }

        $streamTypeSpecificStats = match ($this->getStreamType()) {
            StreamType::HEART_RATE => [
                'maxHeartRate' => max($data),
                'minHeartRate' => min($data),
                'avgHeartRate' => round(array_sum($data) / count($data)),
            ],
            StreamType::WATTS => [
                'maxPower' => max($data),
                'minPower' => min($data),
                'avgPower' => round(array_sum($data) / count($data)),
            ],
            StreamType::VELOCITY => [
                'maxMeterPerSecond' => max($data),
                'minMeterPerSecond' => min($data),
                'avgMeterPerSecond' => round(array_sum($data) / count($data)),
            ],
            default => [],
        };

        return [
            'activityId' => $this->getActivityId()->toUnprefixedString(),
            'streamType' => $this->getStreamType()->value,
            'totalPoints' => count($data),
            ...$streamTypeSpecificStats,
        ];
    }
}
