<?php

namespace App\Tests\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStream;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamTypes;
use App\Infrastructure\Measurement\UnitSystem;
use PHPUnit\Framework\TestCase;

class CombinedActivityStreamTest extends TestCase
{
    public function testGetters(): void
    {
        $stream = CombinedActivityStream::fromState(
            activityId: ActivityId::fromUnprefixed(1),
            unitSystem: UnitSystem::METRIC,
            streamTypes: CombinedStreamTypes::fromArray([CombinedStreamType::VELOCITY]),
            data: [],
            maxYAxisValue: 300,
        );

        $this->assertEmpty($stream->getTimes());
        $this->assertEmpty($stream->getDistances());
        $this->assertEmpty($stream->getCoordinates());

        $stream = CombinedActivityStream::fromState(
            activityId: ActivityId::fromUnprefixed(1),
            unitSystem: UnitSystem::METRIC,
            streamTypes: CombinedStreamTypes::fromArray([
                CombinedStreamType::TIME,
                CombinedStreamType::DISTANCE,
                CombinedStreamType::LAT_LNG,
            ]),
            data: [[1, 2, 3], [3, 2, 1]],
            maxYAxisValue: 300,
        );

        $this->assertEquals(
            [1, 3],
            $stream->getTimes()
        );
        $this->assertEquals(
            [2, 2],
            $stream->getDistances()
        );
        $this->assertEquals(
            [3, 1],
            $stream->getCoordinates()
        );
    }
}
