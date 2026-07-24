<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStream;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamTypes;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;

final class CombinedActivityStreamBuilder
{
    private ActivityId $activityId;
    private UnitSystem $unitSystem = UnitSystem::METRIC;
    private CombinedStreamTypes $streamTypes;
    private array $data;
    private readonly int $maxYAxisValue;

    private function __construct()
    {
        $this->activityId = ActivityId::fromUnprefixed('test');
        $this->streamTypes = CombinedStreamTypes::fromArray([CombinedStreamType::DISTANCE, CombinedStreamType::ALTITUDE]);
        $this->data = Json::decode('[{"distance":0,"altitude":10,"cadence":90,"watts":250,"heartrate":150},{"distance":10,"altitude":10.5,"cadence":87,"watts":260,"heartrate":155},{"distance":15,"altitude":11,"cadence":85,"watts":270,"heartrate":157},{"distance":25,"altitude":12,"cadence":80,"watts":280,"heartrate":162},{"distance":30,"altitude":12.3,"cadence":78,"watts":290,"heartrate":165},{"distance":50,"altitude":13,"cadence":75,"watts":300,"heartrate":168},{"distance":150,"altitude":15,"cadence":65,"watts":340,"heartrate":178}]');
        $this->maxYAxisValue = 10;
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): CombinedActivityStream
    {
        return CombinedActivityStream::create(
            activityId: $this->activityId,
            unitSystem: $this->unitSystem,
            streamTypes: $this->streamTypes,
            data: $this->data,
            maxYAxisValue: $this->maxYAxisValue
        );
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withUnitSystem(UnitSystem $unitSystem): self
    {
        $this->unitSystem = $unitSystem;

        return $this;
    }

    public function withStreamTypes(CombinedStreamTypes $streamTypes): self
    {
        $this->streamTypes = $streamTypes;

        return $this;
    }

    public function withData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
