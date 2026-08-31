<?php

namespace App\Tests\Domain\Activity\Split;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Split\ActivitySplit;
use App\Domain\Activity\Split\ActivitySplitCalculator;
use App\Domain\Activity\Split\ActivitySplits;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class ActivitySplitCalculatorTest extends TestCase
{
    use MatchesSnapshots;

    private ActivitySplitCalculator $activitySplitCalculator;

    #[DataProvider('provideScenarios')]
    public function testCalculate(string $fixture, UnitSystem $unitSystem): void
    {
        $splits = $this->activitySplitCalculator->calculate(
            streams: $this->buildStreams($fixture),
            activityId: ActivityId::fromUnprefixed('1234'),
            unitSystem: $unitSystem,
        );

        $this->assertMatchesJsonSnapshot($this->toSnapshot($splits));
    }

    public static function provideScenarios(): iterable
    {
        yield 'clean run' => ['clean-run.json', UnitSystem::METRIC];
        yield 'clean run imperial' => ['clean-run.json', UnitSystem::IMPERIAL];
        yield 'null times' => ['null-times.json', UnitSystem::METRIC];
        yield 'no altitude' => ['no-altitude.json', UnitSystem::METRIC];
        yield 'no moving stream' => ['no-moving-stream.json', UnitSystem::METRIC];
        yield 'short moving stream' => ['short-moving-stream.json', UnitSystem::METRIC];
        yield 'recording gap' => ['recording-gap.json', UnitSystem::METRIC];
        yield 'non monotonic distance' => ['non-monotonic-distance.json', UnitSystem::METRIC];
        yield 'sprint finish' => ['sprint-finish.json', UnitSystem::METRIC];
        yield 'under one mile' => ['under-one-mile.json', UnitSystem::METRIC];
        yield 'under one mile imperial' => ['under-one-mile.json', UnitSystem::IMPERIAL];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->activitySplitCalculator = new ActivitySplitCalculator();
    }

    private function buildStreams(string $fixture): ActivityStreams
    {
        $streamData = Json::decode(file_get_contents(__DIR__.'/fixtures/'.$fixture));

        $streams = ActivityStreams::empty();
        foreach ($streamData as $streamType => $data) {
            $streams->add(ActivityStreamBuilder::fromDefaults()
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }

        return $streams;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toSnapshot(ActivitySplits $splits): array
    {
        return array_map(
            static fn (ActivitySplit $split): array => [
                'splitNumber' => $split->getSplitNumber(),
                'distance' => $split->getDistance()->toFloat(),
                'elapsedTimeInSeconds' => $split->getElapsedTimeInSeconds(),
                'movingTimeInSeconds' => $split->getMovingTimeInSeconds(),
                'elevationDifference' => $split->getElevationDifference()->toFloat(),
                'averageSpeed' => $split->getAverageSpeed()->toFloat(),
                'minAverageSpeed' => $split->getMinAverageSpeed()->toFloat(),
                'maxAverageSpeed' => $split->getMaxAverageSpeed()->toFloat(),
                'paceZone' => $split->getPaceZone(),
            ],
            $splits->toArray(),
        );
    }
}
