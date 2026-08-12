<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\Metric;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\StreamType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityStreamMetric')]
#[ORM\Index(name: 'ActivityStreamMetric_activityIndex', columns: ['activityId'])]
#[ORM\Index(name: 'ActivityStreamMetric_streamTypeIndex', columns: ['streamType'])]
#[ORM\Index(name: 'ActivityStreamMetric_metricTypeIndex', columns: ['metricType'])]
#[ORM\Index(name: 'ActivityStreamMetric_streamTypeMetricType', columns: ['streamType', 'metricType'])]
final readonly class ActivityStreamMetric
{
    /**
     * @param array<mixed> $data
     */
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Id, ORM\Column(type: 'string')]
        private StreamType $streamType,
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityStreamMetricType $metricType,
        #[ORM\Column(type: 'blob')]
        private array $data,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function create(
        ActivityId $activityId,
        StreamType $streamType,
        ActivityStreamMetricType $metricType,
        array $data,
    ): self {
        return new self(
            activityId: $activityId,
            streamType: $streamType,
            metricType: $metricType,
            data: $data,
        );
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromState(
        ActivityId $activityId,
        StreamType $streamType,
        ActivityStreamMetricType $metricType,
        array $data,
    ): self {
        return new self(
            activityId: $activityId,
            streamType: $streamType,
            metricType: $metricType,
            data: $data,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getStreamType(): StreamType
    {
        return $this->streamType;
    }

    public function getMetricType(): ActivityStreamMetricType
    {
        return $this->metricType;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
