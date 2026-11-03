<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class ActivityStreamBuilder
{
    private ActivityId $activityId;
    private StreamType $streamType = StreamType::WATTS;
    private SerializableDateTime $createdOn;
    /** @var array<int, mixed> */
    private array $data = [];

    private function __construct()
    {
        $this->activityId = ActivityId::fromUnprefixed('1234');
        $this->createdOn = SerializableDateTime::fromString('2023-10-10');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ActivityStream
    {
        return ActivityStream::fromState(
            activityId: $this->activityId,
            streamType: $this->streamType,
            streamData: $this->data,
            createdOn: $this->createdOn,
        );
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withStreamType(StreamType $streamType): self
    {
        $this->streamType = $streamType;

        return $this;
    }

    public function withCreatedOn(SerializableDateTime $createdOn): self
    {
        $this->createdOn = $createdOn;

        return $this;
    }

    /**
     * @param array<int, mixed> $data
     */
    public function withData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
