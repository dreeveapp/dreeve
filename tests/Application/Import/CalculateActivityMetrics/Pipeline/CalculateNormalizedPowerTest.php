<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateNormalizedPower;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\ProvideSnapshotAssertion;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class CalculateNormalizedPowerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideSnapshotAssertion;

    private CalculateNormalizedPower $calculateNormalizedPower;

    public function testProcess(): void
    {
        $output = new SpyOutput();

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(4))
            ->withStreamType(StreamType::WATTS)
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(4))
            ->withStreamType(StreamType::TIME)
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStreamType(StreamType::WATTS)
            ->withData([])
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(5))
            ->withStreamType(StreamType::WATTS)
            ->withData([])
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed(5),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::NORMALIZED_POWER,
            data: [10],
        ));

        $this->calculateNormalizedPower->process($output);

        $this->assertSame(
            "  => Calculated normalized power for 0 streams (3 s)\n"
            ."  => Calculated normalized power for 1 streams (3 s)\n"
            ."  => Calculated normalized power for 1 streams (3 s)\n",
            (string) $output,
        );
        $this->assertCompressedDatabaseQueryMatchesSnapshot(
            'SELECT activityId, streamType, metricType, data FROM ActivityStreamMetric WHERE metricType = :metricType',
            ['metricType' => ActivityStreamMetricType::NORMALIZED_POWER->value],
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateNormalizedPower = $this->getContainer()->get(CalculateNormalizedPower::class);
    }
}
