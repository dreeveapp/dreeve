<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateStreamValueDistribution;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\Metric\ActivityStreamValueDistributionCalculator;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\ProvideSnapshotAssertion;
use App\Tests\SpyOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class CalculateStreamValueDistributionTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideSnapshotAssertion;

    private CalculateStreamValueDistribution $calculateStreamValueDistribution;

    #[DataProvider(methodName: 'provideUnitSystems')]
    public function testProcess(UnitSystem $unitSystem): void
    {
        $output = new SpyOutput();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withSportType(SportType::RUN)
                ->withStartDateTime(SerializableDateTime::fromString('2021-03-01'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(3))
                ->withSportType(SportType::RIDE)
                ->withStartDateTime(SerializableDateTime::fromString('2021-03-01'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withSportType(SportType::POOL_SWIM)
                ->withStartDateTime(SerializableDateTime::fromString('2021-03-01'))
                ->build(),
            []
        ));

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
            ->withActivityId(ActivityId::fromUnprefixed(4))
            ->withStreamType(StreamType::HEART_RATE)
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(4))
            ->withStreamType(StreamType::VELOCITY)
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(4))
            ->withStreamType(StreamType::CADENCE)
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/../../../../fixtures/strava/streams/watts.json')))
            ->build();
        $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);

        $stream = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(3))
            ->withStreamType(StreamType::VELOCITY)
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
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [4 => 3, 2 => 3],
        ));

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsName::UNIT_SYSTEM, $unitSystem->value);

        new CalculateStreamValueDistribution(
            $this->getContainer()->get(ActivityStreamMetricRepository::class),
            $this->getContainer()->get(ActivityStreamValueDistributionCalculator::class),
            $settingsRepository,
        )->process($output);

        $this->assertStringEndsWith("  => Calculated value distribution for 4 streams (3 s)\n", (string) $output);
        $this->assertCompressedDatabaseQueryMatchesSnapshot(
            'SELECT activityId, streamType, metricType, data FROM ActivityStreamMetric WHERE metricType = :metricType',
            ['metricType' => ActivityStreamMetricType::VALUE_DISTRIBUTION->value],
        );
    }

    public static function provideUnitSystems(): iterable
    {
        yield 'metric' => [UnitSystem::METRIC];
        yield 'imperial' => [UnitSystem::IMPERIAL];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateStreamValueDistribution = $this->getContainer()->get(CalculateStreamValueDistribution::class);
    }
}
