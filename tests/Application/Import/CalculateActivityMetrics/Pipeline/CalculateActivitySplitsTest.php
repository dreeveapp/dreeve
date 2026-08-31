<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateActivitySplits;
use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateGap;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class CalculateActivitySplitsTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CalculateActivitySplits $calculateActivitySplits;
    private ActivitySplitRepository $activitySplitRepository;
    private ActivityStreamRepository $activityStreamRepository;
    private ActivityRepository $activityRepository;
    private CalculateGap $calculateGap;

    public function testProcess(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-from-file');
        $this->addActivity($activityId, SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams($activityId, 2500);

        $output = new SpyOutput();
        $this->calculateActivitySplits->process($output);

        $this->assertCount(3, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC));
        $this->assertCount(2, $this->activitySplitRepository->findBy($activityId, UnitSystem::IMPERIAL));
        $this->assertMatchesTextSnapshot($output);
    }

    public function testProcessSkipsActivityImportedFromStrava(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-from-strava');
        $this->addActivity($activityId, SportType::RUN, ImportSource::STRAVA_API);
        $this->addStreams($activityId, 2500);

        $output = new SpyOutput();
        $this->calculateActivitySplits->process($output);

        $this->assertCount(0, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC));
        $this->assertSame('', (string) $output);
    }

    public function testProcessSkipsSportTypeWithoutSplits(): void
    {
        $activityId = ActivityId::fromUnprefixed('ride-from-file');
        $this->addActivity($activityId, SportType::RIDE, ImportSource::FIT_FILE);
        $this->addStreams($activityId, 2500);

        $output = new SpyOutput();
        $this->calculateActivitySplits->process($output);

        $this->assertCount(0, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC));
        $this->assertSame('', (string) $output);
    }

    public function testProcessSkipsActivityThatIsShorterThanOneSplit(): void
    {
        $activityId = ActivityId::fromUnprefixed('short-run-from-file');
        $this->addActivity($activityId, SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams($activityId, 800);

        $output = new SpyOutput();
        $this->calculateActivitySplits->process($output);

        $this->assertCount(0, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC));
        $this->assertCount(0, $this->activitySplitRepository->findBy($activityId, UnitSystem::IMPERIAL));
        $this->assertSame('', (string) $output);
    }

    public function testProcessSkipsActivityWithoutStreams(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-without-streams');
        $this->addActivity($activityId, SportType::RUN, ImportSource::FIT_FILE);

        $output = new SpyOutput();
        $this->calculateActivitySplits->process($output);

        $this->assertCount(0, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC));
        $this->assertSame('', (string) $output);
    }

    public function testProcessDoesNotRecalculateExistingSplits(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-with-splits');
        $this->addActivity($activityId, SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams($activityId, 2500);
        $this->activitySplitRepository->add(ActivitySplitBuilder::fromDefaults()
            ->withActivityId($activityId)
            ->withUnitSystem(UnitSystem::METRIC)
            ->withSplitNumber(1)
            ->build());

        $output = new SpyOutput();
        $this->calculateActivitySplits->process($output);

        $this->assertCount(1, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC));
        $this->assertSame('', (string) $output);
    }

    public function testProcessIsIdempotent(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-from-file');
        $this->addActivity($activityId, SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams($activityId, 2500);

        $this->calculateActivitySplits->process(new SpyOutput());
        $output = new SpyOutput();
        $this->calculateActivitySplits->process($output);

        $this->assertCount(3, $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC));
        $this->assertSame('', (string) $output);
    }

    public function testCalculatedSplitsAreEnrichedWithGapInTheSameRun(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-from-file');
        $this->addActivity($activityId, SportType::RUN, ImportSource::FIT_FILE);
        $this->addStreams($activityId, 2500);

        $this->calculateActivitySplits->process(new SpyOutput());
        $this->calculateGap->process(new SpyOutput());

        $splits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray();
        $this->assertNotNull($splits[0]->getGapPaceInSecondsPerKm());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateActivitySplits = $this->getContainer()->get(CalculateActivitySplits::class);
        $this->activitySplitRepository = $this->getContainer()->get(ActivitySplitRepository::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->calculateGap = $this->getContainer()->get(CalculateGap::class);
    }

    private function addActivity(ActivityId $activityId, SportType $sportType, ImportSource $importSource): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType($sportType)
                ->withImportSource($importSource)
                ->build(),
            [],
        ));
    }

    private function addStreams(ActivityId $activityId, int $totalDistanceInMeters): void
    {
        $distance = [];
        $time = [];
        $altitude = [];
        $latLng = [];
        $sampleCount = (int) ($totalDistanceInMeters / 100);

        for ($i = 0; $i <= $sampleCount; ++$i) {
            $distance[] = $i * 100.0;
            $time[] = $i * 30;
            $altitude[] = 100.0 + $i;
            $latLng[] = [50.0 + $i * 0.0008993, 4.0];
        }

        foreach ([StreamType::DISTANCE->value => $distance, StreamType::TIME->value => $time, StreamType::ALTITUDE->value => $altitude, StreamType::LAT_LNG->value => $latLng] as $streamType => $data) {
            $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }
    }
}
