<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateCombinedStreams;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\ProvideSnapshotAssertion;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class CalculateCombinedStreamsTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideSnapshotAssertion;

    private CalculateCombinedStreams $calculateCombinedStreams;

    public function testProcess(): void
    {
        $output = new SpyOutput();

        $this->provideGeneralTestData(
            sportType: SportType::RIDE,
            omitDistanceStream: false,
        );
        $this->calculateCombinedStreams->process($output);
        $this->assertCompressedDatabaseQueryMatchesSnapshot('SELECT * FROM CombinedActivityStream');

        $this->calculateCombinedStreams->process($output);
        $this->assertMatchesTextSnapshot($output);
    }

    public function testProcessImperial(): void
    {
        $output = new SpyOutput();

        $this->provideGeneralTestData(
            sportType: SportType::RIDE,
            omitDistanceStream: false,
        );

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::APPEARANCE, ['unitSystem' => UnitSystem::IMPERIAL->value]);

        new CalculateCombinedStreams(
            activityRepository: $this->getContainer()->get(ActivityRepository::class),
            combinedActivityStreamRepository: $this->getContainer()->get(CombinedActivityStreamRepository::class),
            activityStreamRepository: $this->getContainer()->get(ActivityStreamRepository::class),
            settingsRepository: $settingsRepository,
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString('2025-12-04'),
                lockName: LockName::IMPORT_DATA_OR_BUILD_APP,
            )
        )->process($output);

        $this->assertCompressedDatabaseQueryMatchesSnapshot('SELECT * FROM CombinedActivityStream');
    }

    public function testProcessWithEmptyDistanceStream(): void
    {
        $output = new SpyOutput();

        $this->provideGeneralTestData(
            sportType: SportType::RIDE,
            omitDistanceStream: true,
        );
        $this->calculateCombinedStreams->process($output);
        $this->assertCompressedDatabaseQueryMatchesSnapshot('SELECT * FROM CombinedActivityStream');
    }

    public function testProcessForRun(): void
    {
        $output = new SpyOutput();

        $this->provideGeneralTestData(
            sportType: SportType::RUN,
            omitDistanceStream: false,
        );
        $this->calculateCombinedStreams->process($output);
        $this->assertCompressedDatabaseQueryMatchesSnapshot('SELECT * FROM CombinedActivityStream');
    }

    public function testProcessWhenStreamDataIsMissingOrEmpty(): void
    {
        $output = new SpyOutput();

        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $streamRepository = $this->getContainer()->get(ActivityStreamRepository::class);

        $activityId = ActivityId::fromUnprefixed('one');
        $activityRepository->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withSportType(SportType::RIDE)
                    ->withActivityId($activityId)
                    ->build(),
                []
            )
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::TIME)
                ->withData([0, 1, 2, 3, 4, 6, 8, 9, 10, 12, 15])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::CADENCE)
                ->withData([])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::WATTS)
                ->withData([0, 145, 168, 175, 172, 0, 0, 180, 185, 0, 192])
                ->build()
        );

        $this->calculateCombinedStreams->process($output);
        $this->assertCompressedDatabaseQueryMatchesSnapshot('SELECT * FROM CombinedActivityStream');
    }

    private function provideGeneralTestData(
        SportType $sportType,
        bool $omitDistanceStream,
    ): void {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $streamRepository = $this->getContainer()->get(ActivityStreamRepository::class);

        $activityId = ActivityId::fromUnprefixed('one');
        $activityRepository->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withSportType($sportType)
                    ->withActivityId($activityId)
                    ->build(),
                []
            )
        );
        if (!$omitDistanceStream) {
            $streamRepository->add(
                ActivityStreamBuilder::fromDefaults()
                    ->withActivityId($activityId)
                    ->withStreamType(StreamType::DISTANCE)
                    ->withData([0.0, 4.5, 9.2, 13.9, 18.5, 18.7, 18.8, 23.4, 28.1, 28.1, 35.0])
                    ->build()
            );
        }
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::ALTITUDE)
                ->withData([12.0, 12.3, 12.8, 13.2, 13.1, 13.0, 13.0, 13.6, 14.1, 14.0, 15.2])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::GRADE)
                ->withData([12.0, 12.3, 12.8, 13.2, 13.1, 13.0, 13.0, 13.6, 14.1, 14.0, 15.2])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::HEART_RATE)
                ->withData([92, 108, 122, 134, 141, 138, 132, 145, 151, 147, 158])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::CADENCE)
                ->withData([0, 78, 82, 84, 83, 0, 0, 85, 87, 0, 89])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::WATTS)
                ->withData([0, 145, 168, 175, 172, 0, 0, 180, 185, 0, 192])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::TIME)
                ->withData([0, 1, 2, 3, 4, 6, 8, 9, 10, 12, 15])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::MOVING)
                ->withData([false, true, true, true, true, false, false, true, true, false, true])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::VELOCITY)
                ->withData([0.0, 4.5, 4.7, 4.6, 4.6, 0.0, 0.0, 4.6, 4.7, 0.0, 4.8])
                ->build()
        );
        $streamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData([
                    [51.200000, 3.216000],
                    [51.200080, 3.216090],
                    [51.200160, 3.216180],
                    [51.200240, 3.216270],
                    null,
                    null,
                    null,
                    [51.200400, 3.216450],
                    [51.200520, 3.216580],
                    null,
                    [51.200700, 3.216900],
                ])
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateCombinedStreams = $this->getContainer()->get(CalculateCombinedStreams::class);
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importDataOrBuildApp', 'value' => '{"lockAcquiredBy": "test"}']
        );
    }
}
