<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateBestStreamAverages;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\ProvideSnapshotAssertion;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class CalculateBestStreamAveragesTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideSnapshotAssertion;

    private CalculateBestStreamAverages $calculateBestStreamAverages;

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
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(10))
            ->withStreamType(StreamType::WATTS)
            ->withData([1, 2])
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $this->calculateBestStreamAverages->process($output);

        $this->assertSame(
            "  => Calculated best averages for 0 streams (3 s)\n"
            ."  => Calculated best averages for 1 streams (3 s)\n"
            ."  => Calculated best averages for 2 streams (3 s)\n"
            ."  => Calculated best averages for 2 streams (3 s)\n",
            (string) $output,
        );
        $this->assertCompressedDatabaseQueryMatchesSnapshot(
            'SELECT activityId, streamType, metricType, data FROM ActivityStreamMetric WHERE metricType = :metricType',
            ['metricType' => ActivityStreamMetricType::BEST_AVERAGES->value],
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateBestStreamAverages = $this->getContainer()->get(CalculateBestStreamAverages::class);
    }
}
